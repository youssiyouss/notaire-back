<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewClient implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $notifiables;

    /**
     * Create a new event instance.
     */
    public function __construct($user, $notifiables)
    {
        $this->user = $user;
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
        return 'NewClientAdded';
    }
}
