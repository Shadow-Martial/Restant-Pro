<?php

namespace App\Notifications;

use App\NotificationChannels\Expo\ExpoChannel;
use App\NotificationChannels\Expo\ExpoMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CallWaiter extends Notification
{
    use Queueable;

    protected $user;

    protected $table;

    protected $msg;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user, $table, $msg)
    {
        $this->user = $user;
        $this->table = $table;
        $this->msg = $msg;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        $notificationClasses = ['database'];
        if ($this->user != null && strlen($this->user->expotoken) > 3) {
            array_push($notificationClasses, ExpoChannel::class);
        }

        return $notificationClasses;
    }

    public function toExpo($notifiable)
    {
        try {
            return ExpoMessage::create()
                ->title($this->msg)
                ->body($this->table->getFullNameAttribute())
                ->badge(1);
        } catch (\Throwable $th) {
            //throw $th;
        }

    }

    public function toDatabase($notifiable)
    {

        return [
            'title' => $this->msg,
            'body' => $this->table->getFullNameAttribute(),
        ];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
