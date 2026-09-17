<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\HandbookDocument;
use App\Utilities\DocumentLoader;
use App\Utilities\HandbookChunker;
use Illuminate\Support\Facades\DB;

class ResyncHandbook
{
    public function __construct(
        private DocumentLoader $loader,
        private HandbookChunker $chunker,
    ) {}

    public function handle(): void
    {
        $documents = $this->loader->documents();
        $slugs = array_column($documents, 'slug');

        DB::transaction(function () use ($documents, $slugs): void {
            foreach ($documents as $document) {
                $handbookDocument = HandbookDocument::query()->updateOrCreate(
                    ['slug' => $document['slug']],
                    [
                        'title' => $document['title'],
                        'body' => $document['body'],
                    ],
                );

                $this->rebuildChunks($handbookDocument);
            }

            $this->deleteMissingDocuments($slugs);
        });
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
    private function deleteMissingDocuments(array $slugs): void
    {
        if ($slugs === []) {
            HandbookDocument::query()->delete();

            return;
        }

        HandbookDocument::query()->whereNotIn('slug', $slugs)->delete();
    }
}
