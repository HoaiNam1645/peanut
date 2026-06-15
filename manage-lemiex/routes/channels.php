<?php

use App\Broadcasting\TicketChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Individual ticket channel (for messages)
Broadcast::channel('ticket.{ticketId}', TicketChannel::class);

// Tickets list channel (for new tickets and updates)
Broadcast::channel('tickets', function ($user) {
    // Allow Admin, Support, and Sellers to listen to this channel
    // Each user will filter data based on their role on frontend
    return $user !== null;
});
