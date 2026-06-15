<?php

namespace App\Broadcasting;

use App\Enums\UserRole;
use App\Models\Support;
use App\Models\User;

class TicketChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     */
    public function join(User $user, int $ticketId): array|bool
    {
        $user->load('role');
        $userRole = $user->role->name ?? null;

        $ticket = Support::with('order:id,seller_id')->find($ticketId);

        if (!$ticket) {
            return false;
        }

        // Admin and Support can access all tickets
        if (in_array($userRole, [UserRole::all()[UserRole::ADMIN], UserRole::all()[UserRole::SUPPORT]])) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $userRole,
            ];
        }

        // Seller can only access their own order's tickets
        if ($userRole === UserRole::all()[UserRole::SELLER]) {
            if ($ticket->order && $ticket->order->seller_id === $user->id) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $userRole,
                ];
            }
        }

        return false;
    }
}
