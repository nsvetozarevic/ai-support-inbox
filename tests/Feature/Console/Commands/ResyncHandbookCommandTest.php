<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Ai\AiCircuitBreaker;
use App\Enums\AiModel;
use App\Exceptions\AiCircuitOpenException;
use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;

class ResyncHandbookCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_resync_updates_handbook_documents_without_wiping_tickets(): void
    {
        $ticket = Ticket::factory()->create();
        $document = HandbookDocument::factory()->create([
            'slug' => 'returns-and-refunds',
            'title' => 'Old title',
            'body' => 'Old body',
        ]);
        $staleChunk = HandbookChunk::factory()->create([
            'handbook_document_id' => $document->id,
            'position' => 0,
            'key' => 'returns-and-refunds:0',
            'body' => 'stale',
        ]);

        Embeddings::fake();

        $this->artisan('handbook:resync')->assertSuccessful();

        $document->refresh();
        $chunks = $document->chunks;

        $this->assertSame('Returns and refunds', $document->title);
        $this->assertStringContainsString('14 days', $document->body);
        $this->assertModelMissing($staleChunk);
        $this->assertGreaterThan(1, $chunks->count());
        $this->assertGreaterThan(
            (int) config('handbook.retrieval.max_chunks'),
            HandbookChunk::query()->count(),
        );
        $this->assertSame(range(0, $chunks->count() - 1), $chunks->pluck('position')->all());
        foreach ($chunks as $chunk) {
            $this->assertSame($document->slug.':'.$chunk->position, $chunk->key);
            $this->assertNotSame('stale', $chunk->body);
            $this->assertSame(hash('sha256', $chunk->body), $chunk->body_hash);
            $this->assertIsArray($chunk->embedding);
            $this->assertCount(1536, $chunk->embedding);
            $this->assertSame(AiModel::TextEmbedding3Small->value, $chunk->embedding_model);
            $this->assertSame(1536, $chunk->embedding_dimensions);
        }
        $this->assertSame(0, HandbookChunk::query()->whereNull('embedding')->count());
        $this->assertModelExists($ticket);
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->model === AiModel::TextEmbedding3Small->value && $prompt->dimensions === 1536 && $prompt->contains('14 days'));
    }

    public function test_resync_removes_documents_that_are_no_longer_in_the_catalog(): void
    {
        $removed = HandbookDocument::factory()->create([
            'slug' => 'retired-policy',
        ]);
        $removedChunk = HandbookChunk::factory()->create([
            'handbook_document_id' => $removed->id,
            'key' => 'retired-policy:0',
            'position' => 0,
        ]);

        Embeddings::fake();

        $this->artisan('handbook:resync')->assertSuccessful();

        $this->assertModelMissing($removed);
        $this->assertModelMissing($removedChunk);
        $this->assertTrue(
            HandbookDocument::query()->where('slug', 'returns-and-refunds')->exists(),
        );
    }

    public function test_resync_leaves_the_database_unchanged_when_a_catalog_document_is_missing_a_slug(): void
    {
        $document = HandbookDocument::factory()->create([
            'slug' => 'kept-policy',
            'title' => 'Unchanged',
            'body' => 'Still here',
        ]);

        config(['handbook.catalog' => $this->catalogPath([
            [
                'title' => 'Broken document',
                'body' => 'This document has no slug.',
            ],
        ])]);

        Embeddings::fake()->preventStrayEmbeddings();

        try {
            $this->artisan('handbook:resync');
            $this->fail('Catalog validation should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('documents.0.slug', $exception->errors());
        }

        Embeddings::assertNothingGenerated();

        $document->refresh();

        $this->assertSame('Unchanged', $document->title);
        $this->assertSame('Still here', $document->body);
        $this->assertDatabaseCount('handbook_documents', 1);
    }

    public function test_resync_leaves_the_database_unchanged_when_embeddings_fail(): void
    {
        $document = HandbookDocument::factory()->create([
            'slug' => 'kept-policy',
            'title' => 'Unchanged',
            'body' => 'Still here',
        ]);

        Embeddings::fake(
            fn (): never => throw RateLimitedException::forProvider(Lab::OpenAI->value),
        );

        try {
            $this->artisan('handbook:resync');
            $this->fail('Embedding failure should stop the resync.');
        } catch (RateLimitedException) {
        }

        $document->refresh();

        $this->assertSame('Unchanged', $document->title);
        $this->assertSame('Still here', $document->body);
        $this->assertFalse(
            HandbookDocument::query()->where('slug', 'returns-and-refunds')->exists(),
        );
        $this->assertDatabaseCount('handbook_documents', 1);
    }

    public function test_an_open_provider_circuit_does_not_embed(): void
    {
        $document = HandbookDocument::factory()->create([
            'slug' => 'kept-policy',
            'title' => 'Unchanged',
            'body' => 'Still here',
        ]);

        Embeddings::fake()->preventStrayEmbeddings();

        (new AiCircuitBreaker)->recordFailure(
            AiModel::TextEmbedding3Small,
            InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        );

        try {
            $this->artisan('handbook:resync');
            $this->fail('An open provider circuit should stop the resync.');
        } catch (AiCircuitOpenException) {
            Embeddings::assertNothingGenerated();
        }

        $document->refresh();

        $this->assertSame('Unchanged', $document->title);
        $this->assertSame('Still here', $document->body);
        $this->assertDatabaseCount('handbook_documents', 1);
    }

    public function test_resync_does_not_embed_a_chunk_whose_text_already_matches_the_model(): void
    {
        config(['handbook.catalog' => $this->catalogPath([
            [
                'title' => 'Old title',
                'slug' => 'returns',
                'body' => 'Fourteen days.',
            ],
        ])]);

        Embeddings::fake();

        $this->artisan('handbook:resync')->assertSuccessful();

        config(['handbook.catalog' => $this->catalogPath([
            [
                'title' => 'New title',
                'slug' => 'returns',
                'body' => 'Fourteen days.',
            ],
        ])]);

        $calls = 0;

        Embeddings::fake(function () use (&$calls): null {
            $calls++;

            return null;
        });

        $this->artisan('handbook:resync')->assertSuccessful();

        $this->assertSame(0, $calls);

        $document = HandbookDocument::query()->where('slug', 'returns')->sole();

        $this->assertSame('New title', $document->title);
        $this->assertSame(1536, count($document->chunks()->sole()->embedding));
    }

    public function test_resync_leaves_the_database_unchanged_when_a_later_batch_fails(): void
    {
        $document = HandbookDocument::factory()->create([
            'slug' => 'kept-policy',
            'title' => 'Unchanged',
            'body' => 'Still here',
        ]);

        config([
            'handbook.catalog' => $this->catalogPath([
                [
                    'title' => 'First',
                    'slug' => 'first',
                    'body' => 'First policy text.',
                ],
                [
                    'title' => 'Second',
                    'slug' => 'second',
                    'body' => 'Second policy text.',
                ],
            ]),
            'handbook.embeddings.batch_size' => 1,
            'handbook.embeddings.concurrency' => 2,
        ]);

        $calls = 0;

        Embeddings::fake(function () use (&$calls): null {
            $calls++;

            if ($calls === 2) {
                throw RateLimitedException::forProvider(Lab::OpenAI->value);
            }

            return null;
        });

        try {
            $this->artisan('handbook:resync');
            $this->fail('A failed batch should stop the resync.');
        } catch (RateLimitedException) {
            $this->assertSame(2, $calls);
        }

        $document->refresh();

        $this->assertSame('Unchanged', $document->title);
        $this->assertSame('Still here', $document->body);
        $this->assertFalse(HandbookDocument::query()->where('slug', 'first')->exists());
        $this->assertDatabaseCount('handbook_documents', 1);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    private function catalogPath(array $documents): string
    {
        $path = sys_get_temp_dir().'/handbook-catalog-'.uniqid('', true).'.php';

        file_put_contents($path, '<?php'.PHP_EOL.PHP_EOL.'declare(strict_types=1);'.PHP_EOL.PHP_EOL.'return '.var_export($documents, true).';'.PHP_EOL);

        return $path;
    }
}
