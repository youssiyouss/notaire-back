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

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $messageId;
    public $receiverId;
    public $senderId;

    public function __construct($messageId, $receiverId, $senderId) {
        $this->messageId = $messageId;
        $this->receiverId = $receiverId;
        $this->senderId = $senderId;
    }

    public function broadcastOn() {
        return [
            new PrivateChannel("chat.{$this->receiverId}"),
            new PrivateChannel("chat.{$this->senderId}"),
        ];
    }

    public function broadcastAs() {
        return 'MessageDeleted';
    }

    public function broadcastWith(): array {
        return [
            'messageId'  => $this->messageId,
            'receiverId' => $this->receiverId,
            'senderId'   => $this->senderId,
        ];
    }

}
