<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportTicketSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public SupportTicket $ticket;

    /**
     * Create a new message instance.
     */
    public function __construct(SupportTicket $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this
            ->subject('New Support Request: ' . $this->ticket->subject)
            ->view('emails.support-ticket-submitted');
    }
}


