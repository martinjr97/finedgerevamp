<?php

use App\Models\SupportTicket;
use App\Models\SupportTicketComment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        SupportTicket::query()->orderBy('id')->chunkById(100, function ($tickets): void {
            foreach ($tickets as $ticket) {
                if ($ticket->assigned_to_id === null && $ticket->handled_by_admin_id) {
                    $ticket->forceFill([
                        'assigned_to_id' => $ticket->handled_by_admin_id,
                        'assigned_at' => $ticket->viewed_at ?? $ticket->updated_at,
                        'last_assigned_at' => $ticket->viewed_at ?? $ticket->updated_at,
                    ])->saveQuietly();
                }

                $hasCustomerComment = SupportTicketComment::query()
                    ->where('support_ticket_id', $ticket->id)
                    ->where('author_type', SupportTicketComment::AUTHOR_CUSTOMER)
                    ->exists();

                if (! $hasCustomerComment && filled($ticket->message)) {
                    SupportTicketComment::query()->create([
                        'support_ticket_id' => $ticket->id,
                        'author_type' => SupportTicketComment::AUTHOR_CUSTOMER,
                        'customer_id' => $ticket->customer_id,
                        'comment' => $ticket->message,
                        'is_internal' => false,
                        'is_visible_to_customer' => true,
                        'metadata' => ['legacy' => true, 'source' => 'initial_message'],
                        'created_at' => $ticket->created_at,
                        'updated_at' => $ticket->created_at,
                    ]);
                }

                if (filled($ticket->resolution_note)) {
                    $hasResolutionComment = SupportTicketComment::query()
                        ->where('support_ticket_id', $ticket->id)
                        ->where('metadata->legacy', true)
                        ->where('metadata->source', 'resolution_note')
                        ->exists();

                    if (! $hasResolutionComment) {
                        SupportTicketComment::query()->create([
                            'support_ticket_id' => $ticket->id,
                            'author_type' => SupportTicketComment::AUTHOR_ADMIN,
                            'admin_id' => $ticket->handled_by_admin_id,
                            'comment' => $ticket->resolution_note,
                            'is_internal' => false,
                            'is_visible_to_customer' => true,
                            'metadata' => ['legacy' => true, 'source' => 'resolution_note'],
                            'created_at' => $ticket->resolved_at ?? $ticket->updated_at,
                            'updated_at' => $ticket->resolved_at ?? $ticket->updated_at,
                        ]);
                    }
                }

                if ($ticket->status === SupportTicket::STATUS_CLOSED && $ticket->closed_at === null) {
                    $ticket->forceFill([
                        'closed_at' => $ticket->resolved_at ?? $ticket->updated_at,
                    ])->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('support_ticket_comments')
            ->where('metadata->legacy', true)
            ->delete();
    }
};
