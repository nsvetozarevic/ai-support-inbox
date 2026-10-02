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

    public function __construct(public Ticket $ticket) {}

    public function handle(TicketDraftClient $client): void
    {
        $client->draft($this->ticket);
    }
}
