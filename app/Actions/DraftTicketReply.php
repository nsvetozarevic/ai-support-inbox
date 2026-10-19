<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\TicketDraft;
use App\Ai\Clients\TicketDraftClient;
use App\Data\TicketDraftData;
use App\Enums\AiPromptLogStatus;
use App\Exceptions\AiCircuitOpenException;
use App\Models\Ticket;
use App\Utilities\HandbookEmbedder;
use App\Utilities\HandbookRetriever;
use Throwable;

class DraftTicketReply
{
    public function __construct(
        private TicketDraft $draftAgent,
        private HandbookEmbedder $embedder,
        private HandbookRetriever $retriever,
        private TicketDraftClient $client,
    ) {}

    /**
     * Embeds the customer message, finds the handbook passages for it, and
     * asks the model for a reply grounded in them. Returns null when the
     * handbook has nothing for this message, or when the draft is not kept.
     */
    public function handle(Ticket $ticket): ?TicketDraftData
    {
        $messageVector = $this->embedMessage($ticket);

        $chunks = $this->retriever->retrieve($ticket->message, $messageVector);

        if ($chunks->isEmpty()) {
            return null;
        }

        return $this->client->draft($ticket, $chunks);
    }

    /**
     * @return list<float>
     */
    private function embedMessage(Ticket $ticket): array
    {
        try {
            return $this->embedder->embedQuery($ticket->message);
        } catch (Throwable $exception) {
            if ($exception instanceof AiCircuitOpenException) {
                throw $exception;
            }

            $this->recordFailedEmbedding($ticket, $exception);

            // If this failure opened the circuit, throw AiCircuitOpenException so the job waits.
            // The provider error stays attached as the previous exception.
            $this->embedder->ensureAvailable($exception);

            throw $exception;
        }
    }

    private function recordFailedEmbedding(Ticket $ticket, Throwable $exception): void
    {
        $ticket->aiPromptLogs()->create([
            'agent' => TicketDraft::class,
            'instructions' => (string) $this->draftAgent->instructions(),
            'input' => $ticket->message,
            'status' => AiPromptLogStatus::Failed,
            'error' => $exception::class.': '.$exception->getMessage(),
        ]);
    }
}
