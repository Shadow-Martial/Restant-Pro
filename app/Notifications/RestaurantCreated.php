<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestaurantCreated extends Notification
{
    use Queueable;

    protected $password;

    protected $restaurant;

    protected $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($password, $restaurant, $user)
    {
        $this->password = $password;
        $this->restaurant = $restaurant;
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
            ->subject(__('notifications_acc_create', ['app_name' => env('APP_NAME', '')]))
            ->line(__('notifications_rest_acc_created', ['restoname' => $this->restaurant->name]))
            ->action(__('notifications_login'), route('login'))
            ->line(__('notifications_username', ['email' => $this->user->email]))
            ->line(__('notifications_password', ['password' => $this->password]))
            ->line(__('notifications_reset_pass'))
            ->line(__('notifications_thanks_for_using_us'));
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
