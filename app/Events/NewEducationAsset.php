<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels; 
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NewEducationAsset implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notifiables;

    /**
     * Create a new event instance.
     */
    public function __construct($notifiables)
    { 
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
        return 'NewDocAdded';
    }
}
