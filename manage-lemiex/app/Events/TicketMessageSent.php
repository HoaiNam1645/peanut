<?php

namespace App\Events;

use App\Models\SupportChat;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $ticketId;

    /**
     * Create a new event instance.
     */
    public function __construct(SupportChat $message, int $ticketId)
    {
        $this->message = $message->load('user:id,username,email,role_id');
        $this->ticketId = $ticketId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ticket.' . $this->ticketId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'user' => [
                    'id' => $this->message->user->id,
                    'username' => $this->message->user->username,
                    'email' => $this->message->user->email,
                    'role_id' => $this->message->user->role_id,
                ],
                'message' => $this->message->message,
                'image_link' => $this->message->image_link,
                'created_at' => $this->message->created_at,
            ]
        ];
    }
}
