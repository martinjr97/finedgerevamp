<?php

namespace App\Notifications;

use App\Support\CommunicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerApprovalNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $pin,
        public string $phone,
        public bool $isActive
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Your '.config('app.name').' Account Has Been Approved';
        $statusLine = $this->isActive
            ? 'Your account has been approved and is now ready for use.'
            : 'Your account has been approved. Access will be enabled once KYC verification is complete.';

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->first_name.'!')
            ->line($statusLine)
            ->line('**Your Login Credentials:**')
            ->line('**Mobile Number:** '.$this->phone)
            ->line('**PIN:** '.$this->pin)
            ->line('Please use your mobile number and PIN to log in to your account.')
            ->line('**Important:** You will be required to change your PIN on your first login for security purposes.')
            ->action('Login to Your Account', route('customer.login'))
            ->line('If you did not expect this message, please contact support immediately.')
            ->salutation('Best regards, '.config('app.name').' Team');

        $this->logToCommunications($notifiable, $subject, $statusLine);

        return $mailMessage;
    }

    /**
     * Log this communication to the communications table.
     */
    private function logToCommunications($notifiable, string $subject, string $statusLine): void
    {
        try {
            $messageContent = "Hello {$notifiable->first_name}!\n\n";
            $messageContent .= $statusLine."\n\n";
            $messageContent .= "Your Login Credentials:\n";
            $messageContent .= "Mobile Number: {$this->phone}\n";
            $messageContent .= "PIN: {$this->pin}\n\n";
            $messageContent .= "Please use your mobile number and PIN to log in to your account.\n\n";
            $messageContent .= "Important: You will be required to change your PIN on your first login for security purposes.\n\n";
            $messageContent .= 'Login to Your Account: '.route('customer.login')."\n\n";
            $messageContent .= 'If you did not expect this message, please contact support immediately.';

            CommunicationLogger::log(
                subject: $subject,
                message: $messageContent,
                type: 'email',
                isSensitive: true,
                recipient: $notifiable,
                createdBy: auth('admin')->user(),
                metadata: [
                    'notification_type' => 'customer_approval',
                    'account_active' => $this->isActive,
                ]
            );
        } catch (\Exception $e) {
            // Communication logging should not block notification delivery.
            \Log::error('Failed to log customer approval communication', [
                'error' => $e->getMessage(),
                'customer_id' => $notifiable->id ?? null,
            ]);
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pin' => $this->pin,
            'phone' => $this->phone,
            'is_active' => $this->isActive,
        ];
    }
}
