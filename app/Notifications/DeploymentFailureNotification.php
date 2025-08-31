<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentFailureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $deploymentData;

    public function __construct(array $deploymentData)
    {
        $this->deploymentData = $deploymentData;
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
            ->error()
            ->subject("❌ Deployment Failed: {$this->deploymentData['app_name']}")
            ->greeting('Deployment Failure Alert')
            ->line("The deployment for **{$this->deploymentData['app_name']}** has failed.")
            ->line("**Deployment ID:** {$this->deploymentData['deployment_id']}")
            ->line("**Environment:** {$this->deploymentData['environment']}")
            ->line("**Error:** {$this->deploymentData['error_message']}")
            ->line("**File:** {$this->deploymentData['error_details']['file']}")
            ->line("**Line:** {$this->deploymentData['error_details']['line']}")
            ->line("**Time:** {$this->deploymentData['timestamp']}")
            ->line('Please check the deployment logs and take appropriate action.')
            ->action('View Deployment Logs', url("/admin/deployments/{$this->deploymentData['deployment_id']}"))
            ->line('An automatic rollback may have been initiated if configured.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->deploymentData;
    }
}