<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Ai\Agents\TicketDraft;
use App\Ai\AiCircuitBreaker;
use App\Ai\Clients\TicketDraftClient;
use App\Enums\AiModel;
use App\Exceptions\AiCircuitOpenException;
use App\Jobs\DraftTicket;
use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Models\Ticket;
use App\Utilities\HandbookRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\RerankingPrompt;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DraftTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_grounded_draft_is_saved_on_the_ticket(): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();
        TicketDraft::fake([
            [
                'reply' => 'We do not take Amex.',
                'quotes' => ['No Amex. Visa and Mastercard only.'],
            ],
        ])->preventStrayPrompts();

        $job = (new DraftTicket($ticket))->withFakeQueueInteractions();

        $this->runThroughMiddleware($job);

        $this->assertSame('We do not take Amex.', $ticket->fresh()->reply);
        $job->assertNotReleased();
        $job->assertNotFailed();
    }

    public function test_a_failed_draft_call_is_released_for_another_try(): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();
        TicketDraft::fake(
            fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = (new DraftTicket($ticket))->withFakeQueueInteractions();

        $this->runThroughMiddleware($job);

        $job->assertReleased(10);
        $job->assertNotFailed();
        $this->assertNull($ticket->fresh()->reply);
    }

    #[DataProvider('closedTickets')]
    public function test_a_closed_ticket_is_skipped_without_searching_or_prompting(string $state): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $ticket = Ticket::factory()->{$state}()->create(['message' => 'Amex']);
        $reply = $ticket->reply;

        Embeddings::fake();
        Reranking::fake()->preventStrayRerankings();
        TicketDraft::fake()->preventStrayPrompts();

        $job = (new DraftTicket($ticket))->withFakeQueueInteractions();

        $this->runThroughMiddleware($job);

        $job->assertNotReleased();
        $job->assertNotFailed();
        $this->assertSame($reply, $ticket->fresh()->reply);
        Embeddings::assertNothingGenerated();
        TicketDraft::assertNeverPrompted();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function closedTickets(): array
    {
        return [
            'sent' => ['sent'],
            'discarded' => ['discarded'],
        ];
    }

    public function test_insufficient_credits_are_rethrown_so_the_draft_job_fails_at_once(): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();
        TicketDraft::fake(
            fn () => throw InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = (new DraftTicket($ticket))->withFakeQueueInteractions();

        try {
            $this->runThroughMiddleware($job);
            $this->fail('Insufficient credits should not be retried.');
        } catch (AiCircuitOpenException) {
            $job->assertNotReleased();
            $job->assertNotFailed();
            $this->assertNull($ticket->fresh()->reply);
        }
    }

    private function runThroughMiddleware(DraftTicket $job): void
    {
        $job->middleware()[0]->handle($job, fn () => $job->handle($this->client()));
    }

    private function client(): TicketDraftClient
    {
        $circuitBreaker = new AiCircuitBreaker;

        return new TicketDraftClient(new TicketDraft, new HandbookRetriever($circuitBreaker), $circuitBreaker);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function storeChunk(string $slug, string $body, array $embedding): HandbookChunk
    {
        $document = HandbookDocument::factory()->create([
            'slug' => $slug,
            'title' => $slug,
            'body' => $body,
        ]);

        return HandbookChunk::factory()->create([
            'handbook_document_id' => $document->id,
            'key' => $slug.':0',
            'position' => 0,
            'body' => $body,
            'embedding' => $embedding,
            'embedding_model' => AiModel::TextEmbedding3Small->value,
            'embedding_dimensions' => 1536,
        ]);
    }

    private function fakeRerankKeepingOrder(): void
    {
        Reranking::fake(function (RerankingPrompt $prompt): array {
            $documents = array_slice(
                $prompt->documents,
                0,
                $prompt->limit ?? count($prompt->documents),
                true,
            );

            $ranked = [];

            foreach ($documents as $index => $document) {
                $ranked[] = new RankedDocument($index, $document, 1.0);
            }

            return $ranked;
        });
    }

    /**
     * @return list<float>
     */
    private function axis(int $dimension): array
    {
        $vector = array_fill(0, 1536, 0.0);
        $vector[$dimension] = 1.0;

        return $vector;
    }
}
