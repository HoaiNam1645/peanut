<?php

namespace App\Events;

use App\Models\Support;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;

    /**
     * Create a new event instance.
     */
    public function __construct(Support $ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to tickets list channel
        return [
            new PrivateChannel('tickets'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $ticket = $this->ticket->load(['order.seller', 'chats.user']);
        $lastChat = $ticket->chats->sortByDesc('created_at')->first();

        return [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'owner' => $ticket->order && $ticket->order->seller ? [
                    'id' => $ticket->order->seller->id,
                    'username' => $ticket->order->seller->username,
                ] : null,
                'order' => $ticket->order ? [
                    'id' => $ticket->order->id,
                    'order_stt' => $ticket->order->order_stt,
                ] : null,
                'user_reply' => $lastChat ? [
                    'id' => $lastChat->user->id,
                    'username' => $lastChat->user->username,
                ] : null,
                'last_reply' => $lastChat ? $lastChat->message : null,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ]
        ];
    }
}
