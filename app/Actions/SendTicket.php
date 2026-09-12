<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;

class SendTicket
{
    public function handle(Ticket $ticket, string $reply): void
    {
        $ticket->update([
            'reply' => $reply,
            'status' => TicketStatus::Sent,
        ]);

        Mail::to($ticket->email)->send(new TicketReplyMail($ticket));
    }
}
