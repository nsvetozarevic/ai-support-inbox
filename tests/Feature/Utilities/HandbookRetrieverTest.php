<?php

declare(strict_types=1);

namespace Tests\Feature\Utilities;

use App\Ai\AiCircuitBreaker;
use App\Enums\AiModel;
use App\Exceptions\AiCircuitOpenException;
use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Utilities\HandbookRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Prompts\RerankingPrompt;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HandbookRetrieverTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('lexicalQueries')]
    public function test_exact_handbook_tokens_return_the_matching_document_slug(string $query, string $slug, string $body): void
    {
        $this->storeChunk($slug, $body, $this->axis(2));
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(1));

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();

        $chunks = $this->retriever()->retrieve($query);

        $this->assertSame($slug, $chunks->first()?->handbookDocument->slug);
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains($query));
    }

    public function test_paraphrase_without_shared_tokens_returns_the_vector_neighbour_slug(): void
    {
        $this->storeChunk('returns-and-refunds', '14 days. Unused. Original packaging.', $this->axis(0));
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(1));

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();

        $chunks = $this->retriever()->retrieve('I want my money back');

        $this->assertSame('returns-and-refunds', $chunks->first()?->handbookDocument->slug);
    }

    public function test_a_chunk_in_both_lists_ranks_above_a_dense_only_neighbour(): void
    {
        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(0));

        Embeddings::fake([[$this->axis(0)]]);
        $this->fakeRerankKeepingOrder();

        $chunks = $this->retriever()->retrieve('Amex');

        $this->assertSame(['payment', 'shipping'], $chunks->pluck('handbookDocument.slug')->all());
    }

    public function test_rerank_order_replaces_the_fused_order_and_keeps_the_limit(): void
    {
        config(['handbook.retrieval.rerank_limit' => 1]);

        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(0));

        Embeddings::fake([[$this->axis(0)]]);
        Reranking::fake(function (RerankingPrompt $prompt): array {
            return [
                new RankedDocument(
                    index: 1,
                    document: $prompt->documents[1],
                    score: 0.9,
                ),
            ];
        });

        $chunks = $this->retriever()->retrieve('Amex');

        $this->assertSame(['shipping'], $chunks->pluck('handbookDocument.slug')->all());
        Reranking::assertReranked(fn (RerankingPrompt $prompt): bool => $prompt->limit === 1
            && $prompt->model === AiModel::RerankV35->value
            && $prompt->contains('Amex')
            && $prompt->documentsContain('No Amex'));
    }

    public function test_a_query_with_no_handbook_matches_does_not_rerank(): void
    {
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(1));

        Embeddings::fake([[$this->axis(0)]]);
        Reranking::fake()->preventStrayRerankings();

        $chunks = $this->retriever()->retrieve('zzzz-not-a-token');

        $this->assertTrue($chunks->isEmpty());
        Reranking::assertNothingReranked();
    }

    public function test_blank_query_returns_no_chunks_and_does_not_embed(): void
    {
        Embeddings::fake()->preventStrayEmbeddings();
        Reranking::fake()->preventStrayRerankings();

        $chunks = $this->retriever()->retrieve('   ');

        $this->assertTrue($chunks->isEmpty());
        Embeddings::assertNothingGenerated();
        Reranking::assertNothingReranked();
    }

    public function test_an_open_embedding_circuit_does_not_embed_or_rerank(): void
    {
        Embeddings::fake()->preventStrayEmbeddings();
        Reranking::fake()->preventStrayRerankings();

        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(
                AiModel::TextEmbedding3Small,
                RateLimitedException::forProvider(Lab::OpenAI->value),
            );
        }

        try {
            $this->retriever()->retrieve('Amex');
            $this->fail('An open embedding circuit should stop retrieval.');
        } catch (AiCircuitOpenException) {
            Embeddings::assertNothingGenerated();
            Reranking::assertNothingReranked();
        }
    }

    public function test_an_open_rerank_circuit_returns_the_fused_order_without_reranking(): void
    {
        config(['handbook.retrieval.rerank_limit' => 1]);

        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(0));

        Embeddings::fake([[$this->axis(0)]]);
        Reranking::fake()->preventStrayRerankings();

        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(
                AiModel::RerankV35,
                RateLimitedException::forProvider(Lab::Cohere->value),
            );
        }

        $chunks = $this->retriever()->retrieve('Amex');

        $this->assertSame(['payment'], $chunks->pluck('handbookDocument.slug')->all());
        Reranking::assertNothingReranked();
    }

    public function test_a_failed_rerank_returns_the_fused_order_and_reports_the_failure(): void
    {
        config(['handbook.retrieval.rerank_limit' => 1]);

        $this->storeChunk('payment', 'No Amex. Visa and Mastercard only.', $this->axis(0));
        $this->storeChunk('shipping', 'Royal Mail from Newcastle in three to five days.', $this->axis(0));

        Embeddings::fake([[$this->axis(0)]]);
        Reranking::fake(fn () => throw RateLimitedException::forProvider(Lab::Cohere->value));
        Exceptions::fake();

        $chunks = $this->retriever()->retrieve('Amex');

        $this->assertSame(['payment'], $chunks->pluck('handbookDocument.slug')->all());
        Exceptions::assertReported(RateLimitedException::class);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function lexicalQueries(): array
    {
        return [
            'order number' => ['#1001', 'order-tracking', 'Every paid order has a number like #1001.'],
            'card brand' => ['Amex', 'payment', 'No American Express. Amex behind Apple Pay fails.'],
            'return window' => ['14 days', 'returns-and-refunds', '14 days. Unused. Original packaging.'],
            'order number in a customer message' => ['Hello, where is my order #1001? It has not arrived yet.', 'order-tracking', 'Every paid order has a number like #1001.'],
            'card brand in a customer message' => ['Hi, do you accept Amex cards for my order?', 'payment', 'No American Express. Amex behind Apple Pay fails.'],
            'return window in a customer message' => ['Can I still send it back after 14 days?', 'returns-and-refunds', '14 days. Unused. Original packaging.'],
        ];
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

    private function retriever(): HandbookRetriever
    {
        return new HandbookRetriever(new AiCircuitBreaker);
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
