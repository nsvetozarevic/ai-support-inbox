<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Ai\AiCircuitBreaker;
use App\Enums\AiModel;
use Illuminate\Support\Facades\Concurrency;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\EmbeddingsCountMismatchException;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Throwable;

class HandbookEmbedder
{
    public function __construct(private AiCircuitBreaker $circuitBreaker) {}

    /**
     * @return list<float>
     */
    public function embedQuery(string $text): array
    {
        return $this->generate([$text], 30)->first();
    }

    /**
     * One embeddings request for every batch, with a limited number in flight.
     * The returned vectors are in the same order as $texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedAll(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $batches = array_chunk($texts, max(1, (int) config('handbook.embeddings.batch_size')));
        $waves = array_chunk($batches, max(1, (int) config('handbook.embeddings.concurrency')));
        $vectors = [];

        foreach ($waves as $wave) {
            $tasks = [];

            foreach ($wave as $batch) {
                $tasks[] = function () use ($batch): array {
                    return app(self::class)->embedChunks($batch)->embeddings;
                };
            }

            foreach (Concurrency::run($tasks) as $embeddings) {
                array_push($vectors, ...$embeddings);
            }
        }

        if (count($vectors) !== count($texts)) {
            throw new EmbeddingsCountMismatchException(count($texts), count($vectors));
        }

        return $vectors;
    }

    /**
     * @param  list<string>  $texts
     */
    private function embedChunks(array $texts): EmbeddingsResponse
    {
        return $this->generate($texts, 60);
    }

    /**
     * A queued caller uses this after a failed embedding. When that failure
     * blocked the provider, this throws AiCircuitOpenException. The failure
     * is not recorded again.
     */
    public function ensureAvailable(Throwable $exception): void
    {
        $this->circuitBreaker->ensureAvailable($this->model(), $exception);
    }

    /**
     * @param  list<string>  $texts
     */
    private function generate(array $texts, int $timeout): EmbeddingsResponse
    {
        $aiModel = $this->model();
        $provider = $aiModel->provider()->value;

        $this->circuitBreaker->ensureAvailable($aiModel);

        try {
            $response = Embeddings::for($texts)
                ->dimensions((int) config('handbook.embeddings.dimensions'))
                ->timeout($timeout)
                ->generate($provider, $aiModel->value);
        } catch (Throwable $exception) {
            $this->circuitBreaker->recordFailure($aiModel, $exception);

            throw $exception;
        }

        $this->circuitBreaker->recordSuccess($aiModel);

        if (count($response->embeddings) !== count($texts)) {
            throw new EmbeddingsCountMismatchException(count($texts), count($response->embeddings));
        }

        return $response;
    }

    private function model(): AiModel
    {
        return AiModel::from((string) config('handbook.embeddings.model'));
    }
}
