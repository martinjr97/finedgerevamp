<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Support\CommunicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminUserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $temporaryPassword,
        protected Admin $admin,
        protected string $loginUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roles = $this->admin->roles->pluck('name')->join(', ') ?: 'No role assigned yet';
        $company = $this->admin->company->name ?? 'Loan Management';
        $subject = 'Your administrator access | '.config('app.system_name');
        $portalHost = parse_url($this->loginUrl, PHP_URL_HOST) ?: 'the admin portal';

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->markdown('emails.admin.invited', [
                'admin' => $this->admin,
                'company' => $company,
                'roles' => $roles,
                'loginUrl' => $this->loginUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'supportEmail' => config('app.support_email'),
                'supportPhone' => config('app.support_phone'),
                'portalHost' => $portalHost,
                'systemName' => config('app.system_name'),
                'appName' => config('app.name'),
            ]);

        // Log to communications (after sending)
        $this->logToCommunications($notifiable, $subject, $roles, $company, $portalHost);

        return $mailMessage;
    }

    /**
     * Log this communication to the communications table
     */
    private function logToCommunications($notifiable, string $subject, string $roles, string $company, string $portalHost): void
    {
        try {
            // Build message content (similar to what's in the email)
            $messageContent = "Hi {$this->admin->full_name}\n\n";
            $messageContent .= "You've been granted administrator access to {$company}.\n";
            $messageContent .= "Roles: {$roles}.\n\n";
            $messageContent .= "Credentials:\n";
            $messageContent .= "- Portal: {$this->loginUrl}\n";
            $messageContent .= "- Email: {$this->admin->email}\n";
            $messageContent .= "- Temporary password: {$this->temporaryPassword}\n\n";
            $messageContent .= "Next steps:\n";
            $messageContent .= "1) Sign in using the details above.\n";
            $messageContent .= "2) Create a new password when prompted.\n";
            $messageContent .= "3) Keep this email private. Only sign in on {$portalHost}.\n\n";
            $messageContent .= "Support: ".config('app.support_email')." | ".config('app.support_phone');

            CommunicationLogger::log(
                subject: $subject,
                message: $messageContent,
                type: 'email',
                isSensitive: true, // Contains temporary password
                recipient: $notifiable,
                createdBy: auth('admin')->user(),
                metadata: [
                    'notification_type' => 'admin_invitation',
                    'company' => $company,
                    'roles' => $roles,
                    'portal_host' => $portalHost,
                ]
            );
        } catch (\Exception $e) {
            // Don't fail the notification if logging fails
            \Log::error('Failed to log admin invitation communication', [
                'error' => $e->getMessage(),
                'admin_id' => $notifiable->id ?? null,
            ]);
        }
    }
}
