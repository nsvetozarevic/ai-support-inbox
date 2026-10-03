<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Clients\TicketTriageClient;
use App\Jobs\Concerns\ManagesAiProvider;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TriageTicket implements ShouldQueue
{
    use ManagesAiProvider, Queueable;

    // One 60s model call. Only a backstop: the call's own timeout should fire first.
    public int $timeout = 90;

    public function __construct(public Ticket $ticket) {}

    public function handle(TicketTriageClient $client): void
    {
        if (! $this->ticket->isOpen()) {
            return;
        }

        $intent = $client->triage($this->ticket);

        if ($intent === null) {
            return;
        }

        $this->ticket->update([
            'intent' => $intent,
        ]);
    }
}
