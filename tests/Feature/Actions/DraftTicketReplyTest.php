<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\DraftTicketReply;
use App\Ai\Agents\TicketDraft;
use App\Ai\AiCircuitBreaker;
use App\Ai\Clients\TicketDraftClient;
use App\Data\TicketDraftData;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Exceptions\AiCircuitOpenException;
use App\Models\AiPromptLog;
use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Models\Ticket;
use App\Utilities\HandbookEmbedder;
use App\Utilities\HandbookRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\RerankingPrompt;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Tests\TestCase;

class DraftTicketReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_retrieved_chunk_is_drafted_from(): void
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

        $draft = $this->action()->handle($ticket);

        $this->assertInstanceOf(TicketDraftData::class, $draft);
        $this->assertSame('We do not take Amex.', $draft->reply);
        TicketDraft::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('No Amex. Visa and Mastercard only.'));
    }

    public function test_a_ticket_with_no_handbook_matches_does_not_prompt(): void
    {
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(1));
        $ticket = Ticket::factory()->create(['message' => 'zzzz-not-a-token']);

        Embeddings::fake([[$this->axis(0)]]);
        Reranking::fake()->preventStrayRerankings();
        TicketDraft::fake()->preventStrayPrompts();

        $draft = $this->action()->handle($ticket);

        $this->assertNull($draft);
        $this->assertNull($ticket->fresh()->reply);
        $this->assertSame(0, AiPromptLog::query()->count());
        TicketDraft::assertNeverPrompted();
    }

    public function test_a_failed_embedding_is_logged_and_rethrows(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake(fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value));
        Reranking::fake()->preventStrayRerankings();
        TicketDraft::fake()->preventStrayPrompts();

        try {
            $this->action()->handle($ticket);
            $this->fail('A failed embedding should rethrow.');
        } catch (RateLimitedException) {
            $this->assertNull($ticket->fresh()->reply);

            $log = AiPromptLog::query()->sole();
            $this->assertSame(TicketDraft::class, $log->agent);
            $this->assertSame('Amex', $log->input);
            $this->assertSame(AiPromptLogStatus::Failed, $log->status);
            $this->assertNull($log->response);
            $this->assertNotNull($log->error);
            TicketDraft::assertNeverPrompted();
        }
    }

    public function test_a_failed_embedding_that_opens_the_circuit_is_logged(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake(fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value));
        Reranking::fake()->preventStrayRerankings();
        TicketDraft::fake()->preventStrayPrompts();

        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 2) as $ignored) {
            $circuitBreaker->recordFailure(
                AiModel::TextEmbedding3Small,
                RateLimitedException::forProvider(Lab::OpenAI->value),
            );
        }

        try {
            $this->action()->handle($ticket);
            $this->fail('A failed embedding should rethrow.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertInstanceOf(RateLimitedException::class, $exception->getPrevious());

            $log = AiPromptLog::query()->sole();
            $this->assertSame('Amex', $log->input);
            $this->assertSame(AiPromptLogStatus::Failed, $log->status);
            TicketDraft::assertNeverPrompted();
        }
    }

    public function test_an_open_embedding_circuit_is_not_logged_as_a_failed_draft(): void
    {
        $ticket = Ticket::factory()->create(['message' => 'Amex']);

        Embeddings::fake()->preventStrayEmbeddings();
        Reranking::fake()->preventStrayRerankings();
        TicketDraft::fake()->preventStrayPrompts();

        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(
                AiModel::TextEmbedding3Small,
                RateLimitedException::forProvider(Lab::OpenAI->value),
            );
        }

        try {
            $this->action()->handle($ticket);
            $this->fail('An open embedding circuit should stop the draft.');
        } catch (AiCircuitOpenException) {
            $this->assertSame(0, AiPromptLog::query()->count());
            Embeddings::assertNothingGenerated();
            TicketDraft::assertNeverPrompted();
        }
    }

    private function action(): DraftTicketReply
    {
        $circuitBreaker = new AiCircuitBreaker;
        $draftAgent = new TicketDraft;

        return new DraftTicketReply(
            $draftAgent,
            new HandbookEmbedder($circuitBreaker),
            new HandbookRetriever($circuitBreaker),
            new TicketDraftClient($draftAgent, $circuitBreaker),
        );
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
