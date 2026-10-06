<?php

declare(strict_types=1);

namespace App\Ai;

use App\Data\AiCircuitStateData;
use App\Enums\AiCircuitOpenReason;
use App\Enums\AiModel;
use App\Exceptions\AiCircuitOpenException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class AiCircuitBreaker
{
    public function ensureAvailable(AiModel $model, ?Throwable $failure = null): void
    {
        $provider = $model->provider()->value;
        $modelName = $model->value;

        $this->throwIfOpen($provider, previous: $failure);
        $this->throwIfOpen($provider, $modelName, $failure);
    }

    public function recordSuccess(AiModel $model): void
    {
        $provider = $model->provider()->value;
        $modelName = $model->value;

        Cache::forget($this->providerKey($provider));
        Cache::forget($this->modelKey($provider, $modelName));
        RateLimiter::clear($this->providerFailureKey($provider));
        RateLimiter::clear($this->modelFailureKey($provider, $modelName));
    }

    public function recordFailure(AiModel $model, Throwable $exception): void
    {
        $provider = $model->provider()->value;
        $modelName = $model->value;

        if ($exception instanceof InsufficientCreditsException || $this->isOpenAiBillingError($exception)) {
            $this->open($this->providerKey($provider), $this->providerOpenSeconds(), AiCircuitOpenReason::InsufficientCredits);

            return;
        }

        if ($exception instanceof ProviderConnectionException) {
            $this->recordBurst(
                $this->providerFailureKey($provider),
                (int) config('ai.circuit_breaker.provider.connection_failures'),
                (int) config('ai.circuit_breaker.provider.connection_window_seconds'),
                $this->providerKey($provider),
                $this->providerOpenSeconds(),
                AiCircuitOpenReason::Connection,
            );

            return;
        }

        if ($exception instanceof RateLimitedException
            || $exception instanceof ProviderOverloadedException
            || $this->isServerError($exception)) {
            $reason = match (true) {
                $exception instanceof RateLimitedException => AiCircuitOpenReason::RateLimit,
                $exception instanceof ProviderOverloadedException => AiCircuitOpenReason::Overloaded,
                default => AiCircuitOpenReason::ServerError,
            };

            $this->recordBurst(
                $this->modelFailureKey($provider, $modelName),
                (int) config('ai.circuit_breaker.model.failures'),
                (int) config('ai.circuit_breaker.model.window_seconds'),
                $this->modelKey($provider, $modelName),
                (int) config('ai.circuit_breaker.model.open_seconds'),
                $reason,
            );
        }
    }

    /**
     * For calls made by queued jobs. When this failure blocked the model or
     * provider, the job gets AiCircuitOpenException and waits for the block
     * instead of counting the failure against its max failures.
     */
    public function recordFailureAndThrowIfOpen(AiModel $model, Throwable $exception): void
    {
        $this->recordFailure($model, $exception);
        $this->ensureAvailable($model, $exception);
    }

    private function isServerError(Throwable $exception): bool
    {
        return $exception instanceof RequestException && $exception->response->serverError();
    }

    /**
     * OpenAI sends billing errors as HTTP 429, which laravel/ai throws as a rate limit.
     */
    private function isOpenAiBillingError(Throwable $exception): bool
    {
        $requestException = $exception->getPrevious();

        return $exception instanceof RateLimitedException
            && $requestException instanceof RequestException
            && $requestException->response->json('error.type') === 'insufficient_quota';
    }

    private function throwIfOpen(string $provider, ?string $model = null, ?Throwable $previous = null): void
    {
        $key = $model === null
            ? $this->providerKey($provider)
            : $this->modelKey($provider, $model);

        $state = $this->state($key);

        if ($state === null) {
            return;
        }

        if ($state->until > now()->getTimestamp()) {
            throw new AiCircuitOpenException($provider, $model, $state->reason, $state->until, $previous);
        }
    }

    private function recordBurst(
        string $failureKey,
        int $threshold,
        int $windowSeconds,
        string $openKey,
        int $openSeconds,
        AiCircuitOpenReason $reason,
    ): void {
        if ($this->isHalfOpen($openKey)) {
            $this->open($openKey, $openSeconds, $reason);

            return;
        }

        if (RateLimiter::increment($failureKey, $windowSeconds) >= $threshold) {
            RateLimiter::clear($failureKey);
            $this->open($openKey, $openSeconds, $reason);
        }
    }

    private function open(string $key, int $seconds, AiCircuitOpenReason $reason): void
    {
        Cache::put(
            $key,
            [
                'until' => now()->addSeconds($seconds)->getTimestamp(),
                'reason' => $reason->value,
            ],
            $seconds + (int) config('ai.circuit_breaker.half_open_seconds'),
        );
    }

    private function state(string $key): ?AiCircuitStateData
    {
        $state = Cache::get($key);

        if (! is_array($state) || ! is_int($state['until'] ?? null) || ! is_string($state['reason'] ?? null)) {
            return null;
        }

        $reason = AiCircuitOpenReason::tryFrom($state['reason']);

        if ($reason === null) {
            return null;
        }

        return new AiCircuitStateData($state['until'], $reason);
    }

    private function isHalfOpen(string $key): bool
    {
        $state = $this->state($key);

        return $state !== null && $state->until <= now()->getTimestamp();
    }

    private function providerOpenSeconds(): int
    {
        return (int) config('ai.circuit_breaker.provider.open_seconds');
    }

    private function providerKey(string $provider): string
    {
        return 'ai-circuit:open:'.$provider;
    }

    private function modelKey(string $provider, string $model): string
    {
        return 'ai-circuit:open:'.$provider.':'.$model;
    }

    private function providerFailureKey(string $provider): string
    {
        return 'ai-circuit:failures:'.$provider;
    }

    private function modelFailureKey(string $provider, string $model): string
    {
        return 'ai-circuit:failures:'.$provider.':'.$model;
    }
}
