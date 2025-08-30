<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CloseAccount extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting(__('notifications_hello', ['username' => $this->user->name]))
            ->line(__('notifications_acc_closed_sub', ['app_name' => config('app.name')]))
            ->line(__('notifications_acc_closed_attachment', ['app_name' => config('app.name')]))
            ->subject(__('notifications_acc_closed', ['app_name' => config('app.name')]))
            ->attachData($this->data, 'data.json', [
                'mime' => 'application/json',
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
