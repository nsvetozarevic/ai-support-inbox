<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\TicketDraft;
use App\Enums\AiPromptLogStatus;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use Illuminate\View\View;

class ShowTicketController extends Controller
{
    public function __invoke(Ticket $ticket): View
    {
        $ticket->load([
            'aiPromptLogs' => fn ($query) => $query->latest(),
        ]);

        return view('tickets.show', [
            'ticket' => $ticket,
            'draftNotice' => $this->draftNotice($ticket),
        ]);
    }

    private function draftNotice(Ticket $ticket): ?string
    {
        $draft = $ticket->aiPromptLogs->first(
            fn (AiPromptLog $log): bool => $log->agent === TicketDraft::class,
        );

        return match ($draft?->status) {
            AiPromptLogStatus::NotCovered => "The handbook doesn't cover this. Write the reply yourself.",
            AiPromptLogStatus::Malformed => 'The draft was rejected because it was badly formed.',
            AiPromptLogStatus::Invalid => 'The draft was rejected because its quotes were not in the handbook.',
            AiPromptLogStatus::Failed => 'The last draft attempt failed.',
            default => null,
        };
    }
}
