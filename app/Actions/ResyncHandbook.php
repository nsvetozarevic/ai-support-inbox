<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\HandbookDocument;
use App\Utilities\HandbookChunker;
use Illuminate\Support\Facades\DB;

class ResyncHandbook
{
    public function __construct(private HandbookChunker $chunker) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $catalog = $this->documents();
            $slugs = array_column($catalog, 'slug');

            foreach ($catalog as $document) {
                $handbookDocument = HandbookDocument::query()->updateOrCreate(
                    ['slug' => $document['slug']],
                    [
                        'title' => $document['title'],
                        'body' => $document['body'],
                    ],
                );

                $this->rebuildChunks($handbookDocument);
            }

            $this->deleteDocumentsMissingFromCatalog($slugs);
        });
    }

    /**
     * @return list<array{title: string, slug: string, body: string}>
     */
    private function documents(): array
    {
        /** @var list<array{title: string, slug: string, body: string}> $documents */
        $documents = require database_path('assets/handbook-documents.php');

        return $documents;
    }

    private function rebuildChunks(HandbookDocument $document): void
    {
        $document->chunks()->delete();

        foreach ($this->chunker->split($document->body) as $position => $body) {
            $document->chunks()->create([
                'key' => $document->slug.':'.$position,
                'position' => $position,
                'body' => $body,
            ]);
        }
    }

    /**
     * @param  list<string>  $slugs
     */
    private function deleteDocumentsMissingFromCatalog(array $slugs): void
    {
        if ($slugs === []) {
            HandbookDocument::query()->delete();

            return;
        }

        HandbookDocument::query()->whereNotIn('slug', $slugs)->delete();
    }
}
