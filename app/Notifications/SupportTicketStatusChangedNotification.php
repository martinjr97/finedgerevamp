<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Support\Queue\ApplicationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected SupportTicket $ticket,
        protected Customer $customer,
        protected string $previousStatus,
        protected string $newStatus,
        protected ?string $staffComment = null,
        protected ?Admin $updatedBy = null,
    ) {
        $this->tries = (int) config('queues.retries.notifications', 3);
        $this->onConnection(ApplicationQueue::connection())
            ->onQueue(ApplicationQueue::notifications());
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketUrl = route('customer.support-tickets.show', $this->ticket);

        return (new MailMessage)
            ->subject('Support ticket #'.$this->ticket->id.' updated | '.config('app.system_name'))
            ->markdown('emails.customer.support-ticket-status-changed-markdown', [
                'ticket' => $this->ticket,
                'customer' => $this->customer,
                'previousStatusLabel' => $this->statusLabel($this->previousStatus),
                'newStatusLabel' => $this->statusLabel($this->newStatus),
                'staffComment' => $this->staffComment,
                'ticketUrl' => $ticketUrl,
                'systemName' => config('app.system_name'),
            ]);
    }

    private function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
