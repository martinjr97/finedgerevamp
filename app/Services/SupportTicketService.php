<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\SupportTicketAssignment;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketComment;
use App\Support\PermissionMatrix;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupportTicketService
{
    public function assignTicket(
        SupportTicket $ticket,
        ?Admin $assignedTo,
        Admin $assignedBy,
        ?string $note = null
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $assignedTo, $assignedBy, $note) {
            $previousAssignedId = $ticket->assigned_to_id;
            $now = now();

            SupportTicketAssignment::query()->create([
                'support_ticket_id' => $ticket->id,
                'assigned_to_id' => $assignedTo?->id,
                'assigned_by_id' => $assignedBy->id,
                'previous_assigned_to_id' => $previousAssignedId,
                'assigned_at' => $now,
                'note' => $note,
            ]);

            $ticket->fill([
                'assigned_to_id' => $assignedTo?->id,
                'assigned_by_id' => $assignedBy->id,
                'assigned_at' => $ticket->assigned_at ?? $now,
                'last_assigned_at' => $now,
                'handled_by_admin_id' => $assignedTo?->id ?? $assignedBy->id,
            ]);

            if ($ticket->status === SupportTicket::STATUS_NEW) {
                $ticket->status = SupportTicket::STATUS_IN_PROGRESS;
            }

            if (! $ticket->viewed_at) {
                $ticket->viewed_at = $now;
            }

            $ticket->save();

            $assigneeName = $assignedTo?->full_name ?? 'Unassigned';
            $message = $assignedTo
                ? "Ticket assigned to {$assigneeName} by {$assignedBy->full_name}."
                : "Ticket unassigned by {$assignedBy->full_name}.";

            if (filled($note)) {
                $message .= ' Note: '.$note;
            }

            $this->addSystemComment($ticket, $message, [
                'event' => 'assignment',
                'assigned_to_id' => $assignedTo?->id,
                'assigned_by_id' => $assignedBy->id,
                'previous_assigned_to_id' => $previousAssignedId,
            ]);

            return $ticket->fresh(['assignedTo', 'assignedBy', 'comments']);
        });
    }

    public function addComment(SupportTicket $ticket, array $payload): SupportTicketComment
    {
        $authorType = $payload['author_type'] ?? null;

        return match ($authorType) {
            SupportTicketComment::AUTHOR_CUSTOMER => $this->addCustomerComment(
                $ticket,
                $payload['customer'],
                $payload['comment']
            ),
            SupportTicketComment::AUTHOR_ADMIN,
            SupportTicketComment::AUTHOR_STAFF => $this->addAdminComment(
                $ticket,
                $payload['admin'],
                $payload['comment'],
                (bool) ($payload['is_internal'] ?? false)
            ),
            SupportTicketComment::AUTHOR_SYSTEM => $this->addSystemComment(
                $ticket,
                $payload['comment'],
                $payload['metadata'] ?? []
            ),
            default => throw new InvalidArgumentException('Invalid comment author type.'),
        };
    }

    public function addCustomerComment(
        SupportTicket $ticket,
        Customer $customer,
        string $comment
    ): SupportTicketComment {
        if ($ticket->customer_id !== $customer->id) {
            throw new InvalidArgumentException('Customer cannot comment on another customer ticket.');
        }

        if (! $ticket->canCustomerComment()) {
            throw new InvalidArgumentException('This ticket is closed and no longer accepts customer replies.');
        }

        if ($ticket->status === SupportTicket::STATUS_NEW) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return SupportTicketComment::query()->create([
            'support_ticket_id' => $ticket->id,
            'author_type' => SupportTicketComment::AUTHOR_CUSTOMER,
            'customer_id' => $customer->id,
            'comment' => $comment,
            'is_internal' => false,
            'is_visible_to_customer' => true,
        ]);
    }

    public function addAdminComment(
        SupportTicket $ticket,
        Admin $admin,
        string $comment,
        bool $isInternal = false
    ): SupportTicketComment {
        if ($ticket->isResolved() || $ticket->isClosed()) {
            if (! $isInternal) {
                throw new InvalidArgumentException('Public replies are not allowed on resolved or closed tickets. Use an internal note instead.');
            }
        }

        $authorType = $this->resolveAdminAuthorType($ticket, $admin);
        $isVisible = ! $isInternal;

        $created = SupportTicketComment::query()->create([
            'support_ticket_id' => $ticket->id,
            'author_type' => $authorType,
            'admin_id' => $admin->id,
            'comment' => $comment,
            'is_internal' => $isInternal,
            'is_visible_to_customer' => $isVisible,
        ]);

        $ticket->update(['handled_by_admin_id' => $admin->id]);

        if ($ticket->status === SupportTicket::STATUS_NEW) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return $created;
    }

    public function addSystemComment(
        SupportTicket $ticket,
        string $comment,
        array $metadata = []
    ): SupportTicketComment {
        return SupportTicketComment::query()->create([
            'support_ticket_id' => $ticket->id,
            'author_type' => SupportTicketComment::AUTHOR_SYSTEM,
            'comment' => $comment,
            'is_internal' => false,
            'is_visible_to_customer' => (bool) ($metadata['customer_visible'] ?? false),
            'metadata' => $metadata,
        ]);
    }

    public function changeStatus(
        SupportTicket $ticket,
        string $status,
        Admin $admin,
        ?string $comment = null,
        ?string $resolutionNote = null
    ): SupportTicket {
        if (! in_array($status, SupportTicket::statuses(), true)) {
            throw new InvalidArgumentException('Invalid ticket status.');
        }

        return DB::transaction(function () use ($ticket, $status, $admin, $comment, $resolutionNote) {
            $previousStatus = $ticket->status;
            $now = now();

            if (filled($comment)) {
                $this->addAdminComment($ticket, $admin, $comment, isInternal: false);
            }

            if (filled($resolutionNote) && $status === SupportTicket::STATUS_RESOLVED) {
                $ticket->resolution_note = $resolutionNote;
                if (blank($comment) || $comment !== $resolutionNote) {
                    $this->addAdminComment($ticket, $admin, $resolutionNote, isInternal: false);
                }
            }

            $ticket->status = $status;
            $ticket->handled_by_admin_id = $admin->id;

            if ($status === SupportTicket::STATUS_RESOLVED) {
                $ticket->resolved_at = $ticket->resolved_at ?? $now;
            }

            if ($status === SupportTicket::STATUS_CLOSED) {
                $ticket->closed_at = $ticket->closed_at ?? $now;
                if (! $ticket->resolved_at) {
                    $ticket->resolved_at = $now;
                }
            }

            if ($status === SupportTicket::STATUS_IN_PROGRESS && $ticket->viewed_at === null) {
                $ticket->viewed_at = $now;
            }

            if (! $ticket->viewed_at) {
                $ticket->viewed_at = $now;
            }

            $ticket->save();

            $fromLabel = ucwords(str_replace('_', ' ', $previousStatus));
            $toLabel = ucwords(str_replace('_', ' ', $status));
            $this->addSystemComment(
                $ticket,
                "Status changed from {$fromLabel} to {$toLabel} by {$admin->full_name}.",
                [
                    'event' => 'status_change',
                    'from' => $previousStatus,
                    'to' => $status,
                    'admin_id' => $admin->id,
                    'customer_visible' => false,
                ]
            );

            return $ticket->fresh(['assignedTo', 'comments']);
        });
    }

    public function resolveTicket(
        SupportTicket $ticket,
        Admin $admin,
        string $resolutionNote,
        ?string $comment = null
    ): SupportTicket {
        return $this->changeStatus(
            $ticket,
            SupportTicket::STATUS_RESOLVED,
            $admin,
            $comment,
            $resolutionNote
        );
    }

    public function closeTicket(
        SupportTicket $ticket,
        Admin $admin,
        ?string $comment = null
    ): SupportTicket {
        return $this->changeStatus(
            $ticket,
            SupportTicket::STATUS_CLOSED,
            $admin,
            $comment
        );
    }

    public function createTicket(array $data, Admin $createdBy): SupportTicket
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $customer = ! empty($data['customer_id'])
                ? Customer::query()->findOrFail($data['customer_id'])
                : null;

            $ticket = SupportTicket::query()->create([
                'customer_id' => $customer?->id,
                'name' => $data['name'] ?? $customer?->full_name ?? 'Guest',
                'email' => $data['email'] ?? $customer?->email,
                'phone' => $data['phone'] ?? $customer?->phone,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'status' => SupportTicket::STATUS_NEW,
                'handled_by_admin_id' => $createdBy->id,
                'viewed_at' => now(),
            ]);

            $this->recordInitialCustomerMessage($ticket);

            $this->addSystemComment(
                $ticket,
                "Ticket created by {$createdBy->full_name}.",
                [
                    'event' => 'created',
                    'admin_id' => $createdBy->id,
                    'customer_visible' => false,
                ]
            );

            if (! empty($data['assigned_to_id'])) {
                $assignee = Admin::query()->findOrFail($data['assigned_to_id']);
                $this->assignTicket(
                    $ticket,
                    $assignee,
                    $createdBy,
                    $data['assignment_note'] ?? 'Assigned on ticket creation.'
                );
            }

            return $ticket->fresh(['customer', 'assignedTo', 'attachments']);
        });
    }

    public function createTicketFromSubmission(
        array $data,
        ?Customer $customer = null,
        ?UploadedFile $attachment = null
    ): SupportTicket {
        return DB::transaction(function () use ($data, $customer, $attachment) {
            $ticket = SupportTicket::query()->create([
                'customer_id' => $customer?->id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'subject' => $data['subject'],
                'message' => $data['message'],
                'status' => SupportTicket::STATUS_NEW,
            ]);

            $this->recordInitialCustomerMessage($ticket);

            if ($attachment) {
                $uploaderType = $customer
                    ? SupportTicketAttachment::UPLOADER_CUSTOMER
                    : SupportTicketAttachment::UPLOADER_GUEST;

                $this->storeAttachment(
                    $ticket,
                    $attachment,
                    $uploaderType,
                    customer: $customer,
                    visibleToCustomer: true
                );
            }

            return $ticket->fresh(['attachments']);
        });
    }

    public function storeAttachment(
        SupportTicket $ticket,
        UploadedFile $file,
        string $uploaderType,
        ?Admin $admin = null,
        ?Customer $customer = null,
        bool $visibleToCustomer = true
    ): SupportTicketAttachment {
        $path = $file->store('support-tickets/'.$ticket->id, 'public');

        $attachment = SupportTicketAttachment::query()->create([
            'support_ticket_id' => $ticket->id,
            'uploader_type' => $uploaderType,
            'admin_id' => $admin?->id,
            'customer_id' => $customer?->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'is_visible_to_customer' => $visibleToCustomer,
        ]);

        $this->addSystemComment(
            $ticket,
            'Supporting file uploaded: '.$attachment->original_name,
            [
                'event' => 'attachment_uploaded',
                'attachment_id' => $attachment->id,
                'customer_visible' => $visibleToCustomer,
            ]
        );

        return $attachment;
    }

    public function recordInitialCustomerMessage(SupportTicket $ticket): SupportTicketComment
    {
        return SupportTicketComment::query()->create([
            'support_ticket_id' => $ticket->id,
            'author_type' => SupportTicketComment::AUTHOR_CUSTOMER,
            'customer_id' => $ticket->customer_id,
            'comment' => $ticket->message,
            'is_internal' => false,
            'is_visible_to_customer' => true,
            'metadata' => ['source' => 'initial_message'],
        ]);
    }

    protected function resolveAdminAuthorType(SupportTicket $ticket, Admin $admin): string
    {
        if ($admin->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)) {
            return SupportTicketComment::AUTHOR_ADMIN;
        }

        if ($ticket->assigned_to_id && (int) $ticket->assigned_to_id === (int) $admin->id) {
            return SupportTicketComment::AUTHOR_STAFF;
        }

        return SupportTicketComment::AUTHOR_ADMIN;
    }
}
