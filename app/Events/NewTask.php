<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewTask
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $notifiable;

    /**
     * Create a new event instance.
     */
    public function __construct($task, $notifiable)
    {
        $this->task = $task;
        $this->notifiable = $notifiable;
    }


    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
        if ($this->notifiable && isset($this->notifiable->id)) {
            return new PrivateChannel('App.Models.User.' . $this->notifiable->id);
        }
    }

    public function broadcastAs()
    {
        return 'NewTask';
    }
}
