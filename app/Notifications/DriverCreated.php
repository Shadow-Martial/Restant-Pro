<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverCreated extends Notification
{
    use Queueable;

    protected $password;

    protected $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($password, $user)
    {
        $this->password = $password;
        $this->user = $user;
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
            ->line(__('notifications_driver_acc_created', ['app_name' => config('app.name')]))
            ->action(__('notifications_login'), url(config('app.url').'/login'))
            ->line(__('notifications_username', ['email' => $this->user->email]))
            ->line(__('notifications_password', ['password' => $this->password]))
            ->line(__('notifications_reset_pass'));
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
