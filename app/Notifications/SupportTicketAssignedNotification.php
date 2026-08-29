<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\SupportTicket;
use App\Support\Queue\ApplicationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupportTicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected SupportTicket $ticket,
        protected Admin $assignedBy,
        protected ?string $assignmentNote = null
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
        $ticketUrl = route('admin.support-tickets.show', $this->ticket);
        $assignedByName = $this->assignedBy->full_name ?: $this->assignedBy->email;

        return (new MailMessage)
            ->subject('Support ticket #'.$this->ticket->id.' assigned to you | '.config('app.system_name'))
            ->markdown('emails.admin.support-ticket-assigned', [
                'ticket' => $this->ticket,
                'assignedByName' => $assignedByName,
                'assignmentNote' => $this->assignmentNote,
                'ticketUrl' => $ticketUrl,
                'systemName' => config('app.system_name'),
            ]);
    }
}
