<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Ai\AiCircuitBreaker;
use App\Enums\AiModel;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\EmbeddingsCountMismatchException;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Throwable;

class HandbookEmbedder
{
    public function __construct(private AiCircuitBreaker $circuitBreaker) {}

    /**
     * @return list<float>|null
     */
    public function embedQuery(string $text): ?array
    {
        return $this->generate([$text], 30)->first();
    }

    /**
     * @param  list<string>  $texts
     */
    public function embedChunks(array $texts): EmbeddingsResponse
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
