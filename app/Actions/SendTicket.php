<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;

class SendTicket
{
    public function handle(Ticket $ticket, string $userReply): void
    {
        if ($userReply === $ticket->reply) {
            $replyQuotes = $ticket->reply_quotes;
            $reply = $ticket->reply;
        } else {
            $replyQuotes = null;
            $reply = $userReply;
        }

        $ticket->reply = $reply;

        Mail::to($ticket->email)->send(new TicketReplyMail($ticket));

        $ticket->update([
            'reply' => $reply,
            'reply_quotes' => $replyQuotes,
            'status' => TicketStatus::Sent,
        ]);
    }
}
