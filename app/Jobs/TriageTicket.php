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

    public function __construct(public Ticket $ticket) {}

    public function handle(TicketTriageClient $client): void
    {
        $intent = $client->triage($this->ticket);

        if ($intent === null) {
            return;
        }

        $this->ticket->update([
            'intent' => $intent,
        ]);
    }
}
