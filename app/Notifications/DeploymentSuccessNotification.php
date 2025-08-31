<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentSuccessNotification extends Notification implements ShouldQueue
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
            ->success()
            ->subject("✅ Deployment Successful: {$this->deploymentData['app_name']}")
            ->greeting('Deployment Success')
            ->line("The deployment for **{$this->deploymentData['app_name']}** has completed successfully.")
            ->line("**Deployment ID:** {$this->deploymentData['deployment_id']}")
            ->line("**Environment:** {$this->deploymentData['environment']}")
            ->line("**Time:** {$this->deploymentData['timestamp']}")
            ->when(isset($this->deploymentData['url']), function ($message) {
                return $message->action('Visit Application', $this->deploymentData['url']);
            })
            ->line('The application is now live and ready for use.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->deploymentData;
    }
}