<?php

declare(strict_types=1);

namespace App\Ai\Clients;

use App\Ai\Agents\TicketTriage;
use App\Ai\AiCircuitBreaker;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Enums\TicketIntent;
use App\Models\Ticket;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

class TicketTriageClient
{
    public function __construct(
        private TicketTriage $triage,
        private AiCircuitBreaker $circuitBreaker,
    ) {}

    public function triage(Ticket $ticket): ?TicketIntent
    {
        $model = AiModel::Gpt4oMini;

        $this->circuitBreaker->ensureAvailable($model);

        try {
            $response = $this->triage->prompt(
                $ticket->message,
                provider: $model->provider(),
                model: $model->value,
            );
        } catch (Throwable $exception) {
            $this->record($ticket, AiPromptLogStatus::Failed, exception: $exception);
            $this->circuitBreaker->recordFailureAndThrowIfOpen($model, $exception);

            throw $exception;
        }

        $this->circuitBreaker->recordSuccess($model);

        $responsePayload = [
            'intent' => $response['intent'] ?? null,
        ];

        $intent = TicketIntent::tryFrom((string) ($responsePayload['intent'] ?? ''));

        if ($intent === null) {
            $this->record($ticket, AiPromptLogStatus::Invalid, $responsePayload, $response->usage);

            return null;
        }

        $this->record($ticket, AiPromptLogStatus::Accepted, $responsePayload, $response->usage);

        return $intent;
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function record(
        Ticket $ticket,
        AiPromptLogStatus $status,
        ?array $responsePayload = null,
        ?Usage $usage = null,
        ?Throwable $exception = null,
    ): void {
        $ticket->aiPromptLogs()->create([
            'agent' => TicketTriage::class,
            'instructions' => (string) $this->triage->instructions(),
            'input' => $ticket->message,
            'response' => $responsePayload,
            'status' => $status,
            'prompt_tokens' => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
            'error' => $exception === null ? null : $exception::class.': '.$exception->getMessage(),
        ]);
    }
}
