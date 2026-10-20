<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Models\Ticket;

class DiscardTicket
{
    /**
     * One conditional update claims the open ticket. A send that claimed it
     * in between matches no open row, so a mailed ticket stays sent.
     */
    public function handle(Ticket $ticket): void
    {
        Ticket::query()
            ->whereKey($ticket->id)
            ->where('status', TicketStatus::New)
            ->update([
                'status' => TicketStatus::Discarded,
            ]);
    }
}
