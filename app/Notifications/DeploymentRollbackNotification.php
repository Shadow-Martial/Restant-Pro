<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentRollbackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $rollbackData;

    public function __construct(array $rollbackData)
    {
        $this->rollbackData = $rollbackData;
    }

    /**
     * Get the notification's delivery channels.
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
        return (new MailMessage)
            ->warning()
            ->subject("🔄 Deployment Rollback: {$this->rollbackData['app_name']}")
            ->greeting('Deployment Rollback Alert')
            ->line("A rollback has been initiated for **{$this->rollbackData['app_name']}**.")
            ->line("**Target Release:** {$this->rollbackData['target_release']}")
            ->line("**Reason:** {$this->rollbackData['reason']}")
            ->line("**Initiated By:** {$this->rollbackData['initiated_by']}")
            ->line("**Environment:** {$this->rollbackData['environment']}")
            ->line("**Time:** {$this->rollbackData['timestamp']}")
            ->line('Please verify that the application is functioning correctly after the rollback.')
            ->action('Check Application Status', url("/admin/deployments"))
            ->line('If issues persist, please investigate immediately.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->rollbackData;
    }
}