<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\HandbookDocument;
use App\Utilities\DocumentLoader;
use App\Utilities\HandbookChunker;
use App\Utilities\HandbookEmbedder;
use Illuminate\Support\Facades\DB;

class ResyncHandbook
{
    public function __construct(
        private DocumentLoader $loader,
        private HandbookChunker $chunker,
        private HandbookEmbedder $embedder,
    ) {}

    public function handle(): void
    {
        $catalog = $this->loader->documents();
        $documents = [];
        $bodies = [];

        foreach ($catalog as $document) {
            $chunks = $this->chunker->split($document['body']);
            $documents[] = [
                'title' => $document['title'],
                'slug' => $document['slug'],
                'body' => $document['body'],
                'chunks' => $chunks,
            ];
            foreach ($chunks as $chunk) {
                $bodies[] = $chunk;
            }
        }

        $embeddings = $bodies === [] ? null : $this->embedder->embedChunks($bodies);

        DB::transaction(function () use ($documents, $embeddings): void {
            $offset = 0;
            $slugs = [];

            foreach ($documents as $document) {
                $handbookDocument = HandbookDocument::query()->updateOrCreate(
                    ['slug' => $document['slug']],
                    [
                        'title' => $document['title'],
                        'body' => $document['body'],
                    ],
                );

                $count = count($document['chunks']);
                $vectors = $embeddings === null
                    ? []
                    : array_slice($embeddings->embeddings, $offset, $count);

                $this->storeChunks(
                    $handbookDocument,
                    $document['chunks'],
                    $vectors,
                    $embeddings?->meta->model,
                    $embeddings === null ? null : count($embeddings->first()),
                );

                $offset += $count;
                $slugs[] = $document['slug'];
            }

            $this->deleteMissingDocuments($slugs);
        });
    }

    /**
     * @param  list<string>  $chunks
     * @param  list<array<int, float>>  $vectors
     */
    private function storeChunks(
        HandbookDocument $document,
        array $chunks,
        array $vectors,
        ?string $model,
        ?int $dimensions,
    ): void {
        $document->chunks()->delete();

        foreach ($chunks as $position => $body) {
            $document->chunks()->create([
                'key' => $document->slug.':'.$position,
                'position' => $position,
                'body' => $body,
                'embedding' => $vectors[$position],
                'embedding_model' => $model,
                'embedding_dimensions' => $dimensions,
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
