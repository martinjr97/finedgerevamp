<?php

namespace App\Http\Controllers;

use App\Mail\SupportTicketSubmitted;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use App\Support\DocumentUploadRules;
use App\Support\ZambianPhoneRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function __construct(
        protected SupportTicketService $supportTicketService
    ) {}

    public function create(): View
    {
        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            $supportTickets = SupportTicket::query()
                ->where('customer_id', $customer->id)
                ->latest('created_at')
                ->limit(10)
                ->get();

            return view('customer.support', compact('customer', 'supportTickets'));
        }

        return view('public.support');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ZambianPhoneRules::nullable(),
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'attachment' => DocumentUploadRules::nullableSupportAttachment(),
        ]);

        $customer = auth('customer')->user();

        $ticket = $this->supportTicketService->createTicketFromSubmission(
            $data,
            $customer instanceof Customer ? $customer : null,
            $request->file('attachment')
        );

        $supportEmail = config('app.support_email', config('mail.from.address'));

        if ($supportEmail) {
            try {
                Mail::to($supportEmail)->send(new SupportTicketSubmitted($ticket));
            } catch (\Throwable $e) {
                Log::warning('Failed to send support ticket email', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($customer) {
            return redirect()
                ->route('customer.support-tickets.show', $ticket)
                ->with('status', 'Your support request has been submitted. Our team will get back to you soon.');
        }

        return redirect()
            ->route('support')
            ->with('status', 'Your support request has been submitted. Our team will get back to you soon.');
    }
}
