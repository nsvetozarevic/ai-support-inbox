<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Models\HandbookChunk;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

class HandbookRetriever
{
    /**
     * @return Collection<int, HandbookChunk>
     */
    public function retrieve(string $query): Collection
    {
        if (trim($query) === '') {
            return collect();
        }

        $maxChunks = (int) config('handbook.retrieval.max_chunks');

        return $this->rerank(
            $this->fuse(
                $this->lexical($query, $maxChunks),
                $this->dense($query, $maxChunks),
                $maxChunks,
            ),
            $query,
        );
    }

    /**
     * @return Collection<int, HandbookChunk>
     */
    private function lexical(string $query, int $limit): Collection
    {
        return HandbookChunk::query()
            ->with('handbookDocument')
            ->whereFullText('body', $query)
            ->orderByRaw(
                "ts_rank(to_tsvector('english', body), plainto_tsquery('english', ?)) desc",
                [$query],
            )
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, HandbookChunk>
     */
    private function dense(string $query, int $limit): Collection
    {
        return HandbookChunk::query()
            ->with('handbookDocument')
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo(
                'embedding',
                $this->embedQuery($query),
                (float) config('handbook.retrieval.min_similarity'),
            )
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<float>
     */
    private function embedQuery(string $query): array
    {
        return Embeddings::for([$query])
            ->dimensions((int) config('handbook.embeddings.dimensions'))
            ->timeout(60)
            ->generate(
                (string) config('ai.default_for_embeddings'),
                (string) config('handbook.embeddings.model'),
            )
            ->first();
    }

    /**
     * @param  Collection<int, HandbookChunk>  $lexical
     * @param  Collection<int, HandbookChunk>  $dense
     * @return Collection<int, HandbookChunk>
     */
    private function fuse(Collection $lexical, Collection $dense, int $limit): Collection
    {
        $k = (int) config('handbook.retrieval.rrf_k');

        return collect([$lexical, $dense])
            ->flatMap(fn (Collection $ranked): Collection => $ranked->values()->map(
                fn (HandbookChunk $chunk, int $rank): array => [
                    'chunk' => $chunk,
                    'score' => 1.0 / ($k + $rank + 1),
                ],
            ))
            ->groupBy(fn (array $row): int => $row['chunk']->id)
            ->map(function (Collection $rows): array {
                $chunk = $rows->first()['chunk'];

                return [
                    'chunk' => $chunk,
                    'id' => $chunk->id,
                    'score' => $rows->sum('score'),
                ];
            })
            ->sortBy([
                ['score', 'desc'],
                ['id', 'asc'],
            ])
            ->take($limit)
            ->pluck('chunk')
            ->values();
    }

    /**
     * @param  Collection<int, HandbookChunk>  $chunks
     * @return Collection<int, HandbookChunk>
     */
    private function rerank(Collection $chunks, string $query): Collection
    {
        if ($chunks->isEmpty()) {
            return $chunks;
        }

        return $chunks->rerank(
            'body',
            $query,
            (int) config('handbook.retrieval.rerank_limit'),
            (string) config('ai.default_for_reranking'),
            (string) config('handbook.reranking.model'),
        )->values();
    }
}
