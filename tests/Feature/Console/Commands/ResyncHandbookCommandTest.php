<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertSame('Returns and refunds', $document->title);
        $this->assertStringContainsString('14 days', $document->body);
        $this->assertModelMissing($staleChunk);
        $this->assertGreaterThan(1, $document->chunks()->count());
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
}
