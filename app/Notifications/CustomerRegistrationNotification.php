<?php

namespace App\Notifications;

use App\Support\CommunicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRegistrationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $pin,
        public string $phone
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
        $subject = 'Welcome to ' . config('app.name') . ' - Your Account Details';
        
        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your account has been successfully created.')
            ->line('**Your Login Credentials:**')
            ->line('**Mobile Number:** ' . $this->phone)
            ->line('**PIN:** ' . $this->pin)
            ->line('Please use your mobile number and PIN to log in to your account.')
            ->line('**Important:** You will be required to change your PIN on your first login for security purposes.')
            ->action('Login to Your Account', route('customer.login'))
            ->line('If you did not request to create this account, please contact support immediately.')
            ->salutation('Best regards, ' . config('app.name') . ' Team');

        // Log to communications (after sending)
        $this->logToCommunications($notifiable, $subject);

        return $mailMessage;
    }

    /**
     * Log this communication to the communications table
     */
    private function logToCommunications($notifiable, string $subject): void
    {
        try {
            // Build message content (similar to what's in the email)
            $messageContent = "Hello {$notifiable->first_name}!\n\n";
            $messageContent .= "Your account has been successfully created.\n\n";
            $messageContent .= "Your Login Credentials:\n";
            $messageContent .= "Mobile Number: {$this->phone}\n";
            $messageContent .= "PIN: {$this->pin}\n\n";
            $messageContent .= "Please use your mobile number and PIN to log in to your account.\n\n";
            $messageContent .= "Important: You will be required to change your PIN on your first login for security purposes.\n\n";
            $messageContent .= "Login to Your Account: " . route('customer.login') . "\n\n";
            $messageContent .= "If you did not create this account, please contact support immediately.";

            CommunicationLogger::log(
                subject: $subject,
                message: $messageContent,
                type: 'email',
                isSensitive: true, // Contains PIN
                recipient: $notifiable,
                createdBy: auth('admin')->user(),
                metadata: [
                    'notification_type' => 'customer_registration',
                ]
            );
        } catch (\Exception $e) {
            // Don't fail the notification if logging fails
            \Log::error('Failed to log customer registration communication', [
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
        ];
    }
}
