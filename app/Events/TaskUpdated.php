<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $task;
    public $notifiables;

    public function __construct($task,$notifiables)
    {
        $this->task = $task;
        $this->notifiables = $notifiables;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        $channels = [];

        foreach ($this->notifiables as $notifiable) {
            if ($notifiable && isset($notifiable->id)) {
                $channels[] = new PrivateChannel('App.Models.User.' . $notifiable->id);
            }
        }

        return $channels;

    }


    public function broadcastAs()
    {
        return 'TaskUpdated';
    }
}
