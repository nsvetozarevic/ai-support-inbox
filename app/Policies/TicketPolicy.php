<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function send(?User $user, Ticket $ticket): bool
    {
        return $ticket->isOpen();
    }

    public function discard(?User $user, Ticket $ticket): bool
    {
        return $ticket->isOpen();
    }
}
