<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTicket
{
    /**
     * One conditional update claims the ticket. The mail goes out after that
     * update has committed, so the row is not locked while the mail server
     * answers, and a sent message is never left for a rollback to undo.
     * A second send matches no open row and stops. If the mail is refused,
     * the ticket is put back to open.
     */
    public function handle(Ticket $ticket, string $userReply): void
    {
        $ticket = Ticket::query()->findOrFail($ticket->id);

        if (! $ticket->isOpen()) {
            return;
        }

        if ($userReply === $ticket->reply) {
            $replyQuotes = $ticket->reply_quotes;
            $reply = $ticket->reply;
        } else {
            $replyQuotes = null;
            $reply = $userReply;
        }

        $previousReply = $ticket->reply;
        $previousQuotes = $ticket->reply_quotes;

        if (! $this->claim($ticket, $reply, $replyQuotes)) {
            return;
        }

        $ticket->reply = $reply;

        try {
            Mail::to($ticket->email)->send(new TicketReplyMail($ticket));
        } catch (Throwable $exception) {
            $this->restore($ticket, $previousReply, $previousQuotes);

            throw $exception;
        }
    }

    /**
     * @param  list<string>|null  $replyQuotes
     */
    private function claim(Ticket $ticket, string $reply, ?array $replyQuotes): bool
    {
        return Ticket::query()
            ->whereKey($ticket->id)
            ->where('status', TicketStatus::New)
            ->update([
                'reply' => $reply,
                'reply_quotes' => $this->encodedQuotes($replyQuotes),
                'status' => TicketStatus::Sent,
            ]) === 1;
    }

    /**
     * @param  list<string>|null  $replyQuotes
     */
    private function restore(Ticket $ticket, ?string $reply, ?array $replyQuotes): void
    {
        Ticket::query()
            ->whereKey($ticket->id)
            ->where('status', TicketStatus::Sent)
            ->update([
                'reply' => $reply,
                'reply_quotes' => $this->encodedQuotes($replyQuotes),
                'status' => TicketStatus::New,
            ]);
    }

    /**
     * @param  list<string>|null  $quotes
     */
    private function encodedQuotes(?array $quotes): ?string
    {
        if ($quotes === null) {
            return null;
        }

        return json_encode(array_values($quotes), JSON_THROW_ON_ERROR);
    }
}
