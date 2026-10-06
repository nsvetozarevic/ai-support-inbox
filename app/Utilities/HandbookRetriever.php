<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Ai\AiCircuitBreaker;
use App\Enums\AiModel;
use App\Exceptions\AiCircuitOpenException;
use App\Models\HandbookChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Throwable;

class HandbookRetriever
{
    public function __construct(private AiCircuitBreaker $circuitBreaker) {}

    /**
     * @return list<float>|null
     */
    public function embed(string $query): ?array
    {
        if (trim($query) === '') {
            return null;
        }

        $aiModel = AiModel::from((string) config('handbook.embeddings.model'));
        $provider = $aiModel->provider()->value;
        $model = $aiModel->value;

        $this->circuitBreaker->ensureAvailable($aiModel);

        try {
            $vector = Embeddings::for([$query])
                ->dimensions((int) config('handbook.embeddings.dimensions'))
                ->timeout(30)
                ->generate($provider, $model)
                ->first();
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailureAndThrowIfOpen($aiModel, $exception);

            throw $exception;
        }

        $this->circuitBreaker->recordSuccess($aiModel);

        return $vector;
    }

    /**
     * @param  list<float>  $vector
     * @return Collection<int, HandbookChunk>
     */
    public function retrieve(string $query, array $vector): Collection
    {
        if (trim($query) === '') {
            return collect();
        }

        $maxChunks = (int) config('handbook.retrieval.max_chunks');

        return $this->rerank(
            $this->fuse(
                $this->lexical($query, $maxChunks),
                $this->dense($vector, $maxChunks),
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
        $anyWord = $this->anyWordQuery($query);

        if ($anyWord === '') {
            return collect();
        }

        return HandbookChunk::query()
            ->with('handbookDocument')
            ->whereFullText('body', $anyWord, ['mode' => 'websearch'])
            ->orderByRaw(
                "ts_rank(to_tsvector('english', body), websearch_to_tsquery('english', ?)) desc",
                [$anyWord],
            )
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Postgres full-text search requires every word by default, and a customer
     * message rarely shares every word with one chunk. Plain words joined with
     * websearch "or" let any word match; ts_rank puts chunks with more matches first.
     *
     * Only letters and digits are kept, because websearch reads a leading "-"
     * as NOT and quotes as a phrase. Postgres still drops stop words and stems.
     */
    private function anyWordQuery(string $query): string
    {
        return Str::of($query)
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->explode(' ')
            ->filter()
            ->implode(' or ');
    }

    /**
     * @param  list<float>  $vector
     * @return Collection<int, HandbookChunk>
     */
    private function dense(array $vector, int $limit): Collection
    {
        return HandbookChunk::query()
            ->with('handbookDocument')
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo(
                'embedding',
                $vector,
                (float) config('handbook.retrieval.vector_min_similarity'),
            )
            ->limit($limit)
            ->get();
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
     * Falls back to the fused order when the reranker is blocked or fails.
     *
     * @param  Collection<int, HandbookChunk>  $chunks
     * @return Collection<int, HandbookChunk>
     */
    private function rerank(Collection $chunks, string $query): Collection
    {
        if ($chunks->isEmpty()) {
            return $chunks;
        }

        $limit = (int) config('handbook.retrieval.rerank_limit');
        $aiModel = AiModel::from((string) config('handbook.reranking.model'));
        $provider = $aiModel->provider()->value;
        $model = $aiModel->value;

        $chunks = $chunks->values();

        try {
            $this->circuitBreaker->ensureAvailable($aiModel);

            $response = Reranking::of($chunks->map(fn (HandbookChunk $chunk): string => $chunk->body)->all())
                ->limit($limit)
                ->rerank($query, $provider, $model);
        } catch (AiCircuitOpenException) {
            return $chunks->take($limit)->values();
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailure($aiModel, $exception);
            report($exception);

            return $chunks->take($limit)->values();
        }

        $this->circuitBreaker->recordSuccess($aiModel);

        $minimum = (float) config('handbook.retrieval.rerank_min_relevance');

        return collect($response->results)
            ->filter(fn (RankedDocument $result): bool => $result->score >= $minimum)
            ->map(fn (RankedDocument $result): HandbookChunk => $chunks[$result->index])
            ->values();
    }
}
