<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Clients;

use App\Ai\Agents\TicketDraft;
use App\Ai\AiCircuitBreaker;
use App\Ai\Clients\TicketDraftClient;
use App\Data\TicketDraftData;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Exceptions\AiCircuitOpenException;
use App\Models\AiPromptLog;
use App\Models\HandbookChunk;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class TicketDraftClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_matching_chunk_is_logged_and_returned(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        TicketDraft::fake([
            [
                'reply' => 'We do not take Amex.',
                'quotes' => ['No Amex. Visa and Mastercard only.'],
            ],
        ])->preventStrayPrompts();

        $draft = $this->client()->draft($ticket, $this->chunks('No Amex. Visa and Mastercard only.'));

        $this->assertInstanceOf(TicketDraftData::class, $draft);
        $this->assertSame('We do not take Amex.', $draft->reply);
        $this->assertSame(['No Amex. Visa and Mastercard only.'], $draft->quotes);
        $this->assertNull($ticket->fresh()->reply);

        TicketDraft::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Amex')
            && $prompt->contains('No Amex. Visa and Mastercard only.')
            && $prompt->model === AiModel::Gpt4oMini->value
            && $prompt->provider()->driver() === Lab::OpenAI->value);

        $log = AiPromptLog::query()->sole();
        $this->assertSame(TicketDraft::class, $log->agent);
        $this->assertSame((string) (new TicketDraft)->instructions(), $log->instructions);
        $this->assertStringContainsString('No Amex. Visa and Mastercard only.', $log->input);
        $this->assertSame(AiPromptLogStatus::Accepted, $log->status);
        $this->assertSame([
            'reply' => 'We do not take Amex.',
            'quotes' => ['No Amex. Visa and Mastercard only.'],
        ], $log->response);
    }

    public function test_a_draft_with_a_non_string_quote_is_not_accepted(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        TicketDraft::fake([
            [
                'reply' => 'We do not take Amex.',
                'quotes' => [12],
            ],
        ])->preventStrayPrompts();

        $draft = $this->client()->draft($ticket, $this->chunks('No Amex. Visa and Mastercard only.'));

        $this->assertNull($draft);
        $this->assertNull($ticket->fresh()->reply);

        $log = AiPromptLog::query()->sole();
        $this->assertStringContainsString('No Amex. Visa and Mastercard only.', $log->input);
        $this->assertSame(AiPromptLogStatus::Malformed, $log->status);
        $this->assertSame([
            'reply' => 'We do not take Amex.',
            'quotes' => [12],
        ], $log->response);
    }

    public function test_a_quote_missing_from_the_handbook_is_not_accepted(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        TicketDraft::fake([
            [
                'reply' => 'We accept Amex.',
                'quotes' => ['We accept Amex.'],
            ],
        ])->preventStrayPrompts();

        $draft = $this->client()->draft($ticket, $this->chunks('No Amex. Visa and Mastercard only.'));

        $this->assertNull($draft);
        $this->assertNull($ticket->fresh()->reply);

        $log = AiPromptLog::query()->sole();
        $this->assertSame(AiPromptLogStatus::Invalid, $log->status);
        $this->assertSame([
            'reply' => 'We accept Amex.',
            'quotes' => ['We accept Amex.'],
        ], $log->response);
    }

    public function test_a_draft_without_quotes_is_logged_as_not_covered(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        TicketDraft::fake([
            [
                'reply' => 'Yes, we accept Amex on every order.',
                'quotes' => [],
            ],
        ])->preventStrayPrompts();

        $draft = $this->client()->draft($ticket, $this->chunks('No Amex. Visa and Mastercard only.'));

        $this->assertNull($draft);
        $this->assertNull($ticket->fresh()->reply);

        $log = AiPromptLog::query()->sole();
        $this->assertSame(AiPromptLogStatus::NotCovered, $log->status);
        $this->assertSame([
            'reply' => 'Yes, we accept Amex on every order.',
            'quotes' => [],
        ], $log->response);
    }

    public function test_a_provider_failure_is_logged_and_rethrows(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        TicketDraft::fake(
            fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        try {
            $this->client()->draft($ticket, $this->chunks('No Amex. Visa and Mastercard only.'));
            $this->fail('A provider failure should rethrow.');
        } catch (RateLimitedException) {
            $this->assertNull($ticket->fresh()->reply);

            $log = AiPromptLog::query()->sole();
            $this->assertStringContainsString('No Amex. Visa and Mastercard only.', $log->input);
            $this->assertSame(AiPromptLogStatus::Failed, $log->status);
            $this->assertNull($log->response);
            $this->assertNotNull($log->error);
        }
    }

    public function test_an_open_chat_circuit_does_not_prompt_for_a_draft(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        TicketDraft::fake()->preventStrayPrompts();

        $circuitBreaker = new AiCircuitBreaker;

        // Circuit open for 3 attempts.
        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(
                AiModel::Gpt4oMini,
                RateLimitedException::forProvider(Lab::OpenAI->value),
            );
        }

        try {
            $this->client()->draft($ticket, $this->chunks('No Amex. Visa and Mastercard only.'));
            $this->fail('An open chat circuit should not prompt for a draft.');
        } catch (AiCircuitOpenException) {
            $this->assertNull($ticket->fresh()->reply);
            $this->assertSame(0, AiPromptLog::query()->count());
            TicketDraft::assertNeverPrompted();
        }
    }

    private function client(): TicketDraftClient
    {
        return new TicketDraftClient(new TicketDraft, new AiCircuitBreaker);
    }

    /**
     * @return Collection<int, HandbookChunk>
     */
    private function chunks(string ...$bodies): Collection
    {
        return collect($bodies)->map(
            fn (string $body): HandbookChunk => HandbookChunk::factory()->make(['body' => $body]),
        );
    }
}
