<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Auth;

class NewTaskNotification extends Notification
{
    use Queueable;

    public $task;
    public $message;

    public function __construct($task, $message)
    {
        $this->task = $task;
        $this->message = $message;
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
            'title' => "Nouvelle tâche ajoutée",
            'message' => $this->message,
            'task' => $this->task,
            'link' => "gestionnaire-de-taches",
            'created_by' => Auth::id(),
            'user_nom'  =>  Auth::user()->nom,
            'user_prenom' => Auth::user()->prenom,
            'user_picture' => Auth::user()->picture,
            'created_at' => now()
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'title' => "Nouvelle tâche ajoutée",
            'message' => $this->message,
            'task' => $this->task,
        ]);
    }

}
