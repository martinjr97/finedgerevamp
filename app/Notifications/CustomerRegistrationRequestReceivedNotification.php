<?php

namespace App\Notifications;

use App\Support\Queue\ApplicationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRegistrationRequestReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $fullName,
        public string $reference,
        public string $registrationPathLabel,
    ) {
        $this->tries = (int) config('queues.retries.notifications', 3);
        $this->onConnection(ApplicationQueue::connection())
            ->onQueue(ApplicationQueue::notifications());
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Registration Request Received ({$this->reference})")
            ->greeting("Hello {$this->fullName},")
            ->line("We have received your {$this->registrationPathLabel} registration request.")
            ->line("Your Registration Request ID is: {$this->reference}")
            ->line('Please keep this ID for your records. You can quote it when following up with our team.')
            ->line('Our team will review your details and contact you with next steps within 48 hours.')
            ->salutation('Regards, '.config('app.name'));
    }
}
