<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRegistrationRequestRevertedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $fullName,
        public string $reference,
        public string $registrationPathLabel,
        public string $reason,
        public string $editUrl
    ) {
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
            ->subject("Action Required: Update Your Registration Request ({$this->reference})")
            ->greeting("Hello {$this->fullName},")
            ->line("Your {$this->registrationPathLabel} registration request has been reverted for editing.")
            ->line('Reason / instructions from the reviewer:')
            ->line($this->reason)
            ->action('Update Your Request', $this->editUrl)
            ->line("If you can't access the link, you can retrieve your request using this ID: {$this->reference}.")
            ->salutation('Regards, ' . config('app.name'));
    }
}

