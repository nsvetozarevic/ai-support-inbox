<?php

declare(strict_types=1);

namespace App\Ai\Clients;

use App\Ai\Agents\TicketDraft;
use App\Ai\AiCircuitBreaker;
use App\Data\TicketDraftData;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Exceptions\InvalidTicketDraftException;
use App\Models\HandbookChunk;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\Usage;
use Throwable;

class TicketDraftClient
{
    public function __construct(
        private TicketDraft $draft,
        private AiCircuitBreaker $circuitBreaker,
    ) {}

    /**
     * @param  Collection<int, HandbookChunk>  $chunks
     */
    public function draft(Ticket $ticket, Collection $chunks): ?TicketDraftData
    {
        $prompt = $this->promptFor($ticket, $chunks);
        $model = AiModel::Gpt4oMini;

        $this->circuitBreaker->ensureAvailable($model);

        try {
            $response = $this->draft->prompt(
                $prompt,
                provider: $model->provider(),
                model: $model->value,
            );
        } catch (Throwable $exception) {
            $this->record($ticket, AiPromptLogStatus::Failed, $prompt, exception: $exception);
            $this->circuitBreaker->recordFailureAndThrowIfOpen($model, $exception);

            throw $exception;
        }

        $this->circuitBreaker->recordSuccess($model);

        $draft = $this->generateResponse($ticket, $prompt, $response->toArray(), $response->usage);

        if ($draft === null) {
            return null;
        }

        $groundedDraft = $this->ground($ticket, $prompt, $draft, $chunks, $response->usage);

        if ($groundedDraft === null) {
            return null;
        }

        $this->record($ticket, AiPromptLogStatus::Accepted, $prompt, $groundedDraft->toArray(), $response->usage);

        return $groundedDraft;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generateResponse(Ticket $ticket, string $prompt, array $payload, Usage $usage): ?TicketDraftData
    {
        try {
            return TicketDraftData::from($payload);
        } catch (InvalidTicketDraftException) {
            $this->record($ticket, AiPromptLogStatus::Malformed, $prompt, $payload, $usage);

            return null;
        }
    }

    /**
     * @param  Collection<int, HandbookChunk>  $chunks
     */
    private function ground(Ticket $ticket, string $prompt, TicketDraftData $draft, Collection $chunks, Usage $usage): ?TicketDraftData
    {
        if ($draft->quotes === []) {
            $this->record($ticket, AiPromptLogStatus::NotCovered, $prompt, $draft->toArray(), $usage);

            return null;
        }

        if (! $this->quotesAppearInChunks($draft, $chunks)) {
            $this->record($ticket, AiPromptLogStatus::Invalid, $prompt, $draft->toArray(), $usage);

            return null;
        }

        return $draft;
    }

    /**
     * @param  Collection<int, HandbookChunk>  $chunks
     */
    private function quotesAppearInChunks(TicketDraftData $draft, Collection $chunks): bool
    {
        if ($draft->quotes === []) {
            return false;
        }

        foreach ($draft->quotes as $quote) {
            if (trim($quote) === '') {
                return false;
            }

            $appears = $chunks->contains(
                fn (HandbookChunk $chunk): bool => str_contains($chunk->body, $quote),
            );

            if (! $appears) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, HandbookChunk>  $chunks
     */
    private function promptFor(Ticket $ticket, Collection $chunks): string
    {
        $excerpts = $chunks->values()->map(
            fn (HandbookChunk $chunk, int $index): string => sprintf('[%d]%s%s', $index + 1, PHP_EOL, $chunk->body),
        )->implode(PHP_EOL.PHP_EOL);

        return sprintf(
            'Customer message:%s%s%s%sHandbook excerpts:%s%s',
            PHP_EOL,
            $ticket->message,
            PHP_EOL,
            PHP_EOL,
            PHP_EOL,
            $excerpts,
        );
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function record(
        Ticket $ticket,
        AiPromptLogStatus $status,
        string $prompt,
        ?array $responsePayload = null,
        ?Usage $usage = null,
        ?Throwable $exception = null,
    ): void {
        $ticket->aiPromptLogs()->create([
            'agent' => TicketDraft::class,
            'instructions' => (string) $this->draft->instructions(),
            'input' => $prompt,
            'response' => $responsePayload,
            'status' => $status,
            'prompt_tokens' => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
            'error' => $exception === null ? null : $exception::class.': '.$exception->getMessage(),
        ]);
    }
}
