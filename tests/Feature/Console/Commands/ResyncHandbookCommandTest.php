<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResyncHandbookCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_resync_updates_handbook_pages_without_wiping_tickets(): void
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

        $this->artisan('handbook:resync')->assertSuccessful();

        $document->refresh();
        $chunks = $document->chunks;

        $this->assertSame('Returns and refunds', $document->title);
        $this->assertStringContainsString('14 days', $document->body);
        $this->assertModelMissing($staleChunk);
        $this->assertGreaterThan(1, $chunks->count());
        $this->assertSame(range(0, $chunks->count() - 1), $chunks->pluck('position')->all());
        foreach ($chunks as $chunk) {
            $this->assertSame($document->slug.':'.$chunk->position, $chunk->key);
            $this->assertNotSame('stale', $chunk->body);
        }
        $this->assertModelExists($ticket);
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

        $this->artisan('handbook:resync')->assertSuccessful();

        $this->assertModelMissing($removed);
        $this->assertModelMissing($removedChunk);
        $this->assertTrue(
            HandbookDocument::query()->where('slug', 'returns-and-refunds')->exists(),
        );
    }

    public function test_resync_leaves_the_database_unchanged_when_a_catalog_page_is_missing_a_slug(): void
    {
        $document = HandbookDocument::factory()->create([
            'slug' => 'kept-policy',
            'title' => 'Unchanged',
            'body' => 'Still here',
        ]);

        config(['handbook.catalog' => $this->catalogPath([
            [
                'title' => 'Broken page',
                'body' => 'This page has no slug.',
            ],
        ])]);

        try {
            $this->artisan('handbook:resync');
            $this->fail('Catalog validation should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('documents.0.slug', $exception->errors());
        }

        $document->refresh();

        $this->assertSame('Unchanged', $document->title);
        $this->assertSame('Still here', $document->body);
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
