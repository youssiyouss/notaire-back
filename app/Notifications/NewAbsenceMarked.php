<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class NewAbsenceMarked extends Notification
{
    use Queueable;
    public $message;
    public $link;
    public $title;


    /**
     * Create a new notification instance.
     */

    public function __construct($message, $link, $title)
    {
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'created_by' => Auth::id(),
            'user_nom'  =>  Auth::user()->nom,
            'user_prenom' => Auth::user()->prenom,
            'user_picture' => Auth::user()->picture,
            'created_at' => now(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link
        ]);
    }
}
