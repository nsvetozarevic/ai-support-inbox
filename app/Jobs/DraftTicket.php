<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Clients\TicketDraftClient;
use App\Data\TicketDraftData;
use App\Enums\TicketStatus;
use App\Jobs\Concerns\ManagesAiProvider;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DraftTicket implements ShouldQueue
{
    use ManagesAiProvider, Queueable;

    // Worst case of the calls: query embedding 30s + rerank 30s + draft 60s = 120s.
    // Only a backstop: each call's own timeout should fire first. Keep it below queue retry_after (160s),
    // which belongs to the queue connection, not to a single job.
    public int $timeout = 140;

    public function __construct(public Ticket $ticket) {}

    public function handle(TicketDraftClient $client): void
    {
        if (! $this->ticket->isOpen()) {
            return;
        }

        $draft = $client->draft($this->ticket);

        if ($draft === null) {
            return;
        }

        $this->suggestReply($draft);
    }

    /**
     * The ticket is refreshed first, because staff may have sent or discarded it
     * while the model was running. The update repeats those checks, so a change
     * in that last moment is not overwritten.
     */
    private function suggestReply(TicketDraftData $draft): void
    {
        $this->ticket->refresh();

        if (! $this->ticket->isOpen() || trim((string) $this->ticket->reply) !== '') {
            return;
        }

        Ticket::query()
            ->whereKey($this->ticket->id)
            ->where('status', TicketStatus::New)
            ->where('reply', $this->ticket->reply)
            ->update([
                'reply' => $draft->reply,
                'reply_quotes' => json_encode($draft->quotes, JSON_THROW_ON_ERROR),
            ]);
    }
}
