<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Ai\Agents\TicketDraft;
use App\Ai\Clients\TicketDraftClient;
use App\Jobs\DraftTicket;
use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Models\Ticket;
use App\Utilities\HandbookRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\RerankingPrompt;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
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

    public function test_retryable_provider_failure_is_released(): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();
        TicketDraft::fake(
            fn () => throw RateLimitedException::forProvider('openai'),
        )->preventStrayPrompts();

        $job = (new DraftTicket($ticket))->withFakeQueueInteractions();

        $this->runThroughMiddleware($job);

        $job->assertReleased(20);
        $job->assertNotFailed();
        $this->assertNull($ticket->fresh()->reply);
    }

    public function test_non_retryable_provider_failure_is_not_caught(): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();
        TicketDraft::fake(
            fn () => throw InsufficientCreditsException::forProvider('openai'),
        )->preventStrayPrompts();

        $job = (new DraftTicket($ticket))->withFakeQueueInteractions();

        try {
            $this->runThroughMiddleware($job);
            $this->fail('Insufficient credits should not be caught by the job.');
        } catch (InsufficientCreditsException) {
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
        return new TicketDraftClient(new TicketDraft, new HandbookRetriever);
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
            'embedding_model' => 'text-embedding-3-small',
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
