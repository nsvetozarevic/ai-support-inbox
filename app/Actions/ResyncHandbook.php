<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\HandbookChunk;
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

        $vectors = $this->vectorsFor($bodies);

        DB::transaction(function () use ($documents, $vectors): void {
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

                $this->storeChunks(
                    $handbookDocument,
                    $document['chunks'],
                    array_slice($vectors, $offset, $count),
                    (string) config('handbook.embeddings.model'),
                    (int) config('handbook.embeddings.dimensions'),
                );

                $offset += $count;
                $slugs[] = $document['slug'];
            }

            $this->deleteMissingDocuments($slugs);
        });
    }

    /**
     * Copies a stored vector when the chunk text and embedding model already match.
     *
     * @param  list<string>  $bodies
     * @return list<list<float>>
     */
    private function vectorsFor(array $bodies): array
    {
        if ($bodies === []) {
            return [];
        }

        $hashes = [];

        foreach ($bodies as $position => $body) {
            $hashes[$position] = hash('sha256', $body);
        }

        $currentChunksVectors = $this->currentChunksVectors(array_values($hashes));
        $missing = [];
        $missingPositions = [];
        $vectors = [];

        foreach ($hashes as $position => $hash) {
            if (array_key_exists($hash, $currentChunksVectors)) {
                $vectors[$position] = $currentChunksVectors[$hash];

                continue;
            }

            $missing[] = $bodies[$position];
            $missingPositions[] = $position;
        }

        $embedded = $missing === [] ? [] : $this->embedder->embedAll($missing);

        foreach ($missingPositions as $embeddedIndex => $position) {
            $vectors[$position] = $embedded[$embeddedIndex];
        }

        // PHP walks an array in insertion order. A later copied vector can be
        // written before an earlier embedded one, so sort by position first.
        ksort($vectors);

        return $vectors;
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, list<float>>
     */
    private function currentChunksVectors(array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        return HandbookChunk::query()
            ->where('embedding_model', config('handbook.embeddings.model'))
            ->where('embedding_dimensions', (int) config('handbook.embeddings.dimensions'))
            ->whereIn('body_hash', $hashes)
            ->whereNotNull('embedding')
            ->get(['body_hash', 'embedding'])
            ->mapWithKeys(fn (HandbookChunk $chunk): array => [$chunk->body_hash => $chunk->embedding])
            ->all();
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
                'body_hash' => hash('sha256', $body),
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
