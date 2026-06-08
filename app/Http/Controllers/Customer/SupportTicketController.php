<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketService $supportTicketService
    ) {}

    public function show(SupportTicket $supportTicket): View
    {
        $customer = auth('customer')->user();
        $this->authorizeForUser($customer, 'viewAsCustomer', $supportTicket);

        $supportTicket->load([
            'comments' => fn ($query) => $query->customerVisible()->orderBy('created_at'),
            'comments.customer',
            'comments.admin',
            'customerVisibleAttachments',
        ]);

        return view('customer.support-tickets.show', [
            'ticket' => $supportTicket,
            'canComment' => $customer->can('commentAsCustomer', $supportTicket),
        ]);
    }

    public function storeComment(SupportTicket $supportTicket): RedirectResponse
    {
        $customer = auth('customer')->user();
        $this->authorizeForUser($customer, 'commentAsCustomer', $supportTicket);

        $data = request()->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->supportTicketService->addCustomerComment(
                $supportTicket,
                $customer,
                $data['comment']
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['comment' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('customer.support-tickets.show', $supportTicket)
            ->with('status', 'Your reply has been sent.');
    }

    public function downloadAttachment(
        SupportTicket $supportTicket,
        SupportTicketAttachment $attachment
    ): StreamedResponse {
        $customer = auth('customer')->user();
        $this->authorizeForUser($customer, 'viewAsCustomer', $supportTicket);

        abort_unless((int) $attachment->support_ticket_id === (int) $supportTicket->id, 404);
        abort_unless($attachment->is_visible_to_customer, 403);
        abort_unless(Storage::disk('public')->exists($attachment->path), 404);

        return Storage::disk('public')->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream']
        );
    }
}
