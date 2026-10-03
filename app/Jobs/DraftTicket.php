<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Clients\TicketDraftClient;
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

        $client->draft($this->ticket);
    }
}
