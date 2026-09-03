<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffOnboardedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $tempPassword;
    protected $userName;

    public function __construct($userName, $tempPassword)
    {
        $this->userName = $userName;
        $this->tempPassword = $tempPassword;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $loginUrl = "http://localhost:3003/login"; // Designated Staff Port

        return (new MailMessage)
            ->subject('Welcome to the Team | Your DM-Tech Credentials')
            ->greeting('Hello, ' . $this->userName . '!')
            ->line('You have been added to the DM-Tech Hotel SaaS platform as a new staff member.')
            ->line('To ensure your account security, we have generated a temporary password for your first login.')
            ->action('Access Staff Portal', $loginUrl)
            ->line('Temporary Password: ' . $this->tempPassword)
            ->line('Important: For security reasons, you will be required to change your password and set a 4-digit Security PIN upon your first login.')
            ->line('If you did not expect this invitation, please contact your manager immediately.')
            ->salutation('Best regards, The DM-Tech Team');
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => 'Staff onboarding credentials sent.',
            'user_name' => $this->userName,
        ];
    }
}
