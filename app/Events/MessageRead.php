<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $readerId;
    public $senderId;

    /**
     * Create a new event instance.
     */
    public function __construct($readerId, $senderId)
    {
        $this->readerId = $readerId; // the one who read
        $this->senderId = $senderId; // the message owner (to notify)
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn() {
        return  new PrivateChannel("chat.{$this->senderId}");
    }

    public function broadcastAs() {
        return 'MessageRead';
    }

    /**
     * Data sent with the broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'readerId' => $this->readerId,
            'senderId' => $this->senderId,
        ];
    }
}
