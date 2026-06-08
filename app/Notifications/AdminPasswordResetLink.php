<?php

namespace App\Notifications;

use App\Support\CommunicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetLink extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $resetUrl,
        public bool $isAdminInitiated = false
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
        $subject = 'Password Reset Instructions - ' . config('app.name');
        
        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->full_name . '!');
        
        if ($this->isAdminInitiated) {
            $message->line('An administrator has initiated a password reset for your account.')
                ->line('Click the button below to reset your password. This link will expire in 1 hour.');
        } else {
            $message->line('You have successfully verified your identity using the OTP sent to your phone.')
                ->line('Click the button below to reset your password. This link will expire in 1 hour.');
        }
        
        $mailMessage = $message
            ->action('Reset Password', $this->resetUrl)
            ->line('If you did not request a password reset, please ignore this email or contact support if you have concerns.')
            ->line('**Security Note:** This link can only be used once. If you need to reset your password again, please contact an administrator or go through the forgot password process.')
            ->salutation('Best regards, ' . config('app.name') . ' Team');

        // Note: Communication logging is now handled in the controller before queuing
        // This ensures it's logged immediately and has access to the authenticated admin

        return $mailMessage;
    }


    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reset_url' => $this->resetUrl,
        ];
    }
}

