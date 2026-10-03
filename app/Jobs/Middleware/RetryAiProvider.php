<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Enums\AiCircuitOpenReason;
use App\Exceptions\AiCircuitOpenException;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * A blocked provider makes the job wait, and the wait does not count.
 * A known provider failure is retried and counted; after max_failures the job fails.
 * Anything else is rethrown, and maxExceptions = 1 fails the job.
 */
class RetryAiProvider
{
    /**
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (AiCircuitOpenException $exception) {
            if ($exception->reason === AiCircuitOpenReason::InsufficientCredits) {
                throw $exception;
            }

            $job->release(
                max(1, $exception->until - now()->getTimestamp())
                    + random_int(0, (int) config('ai.circuit_breaker.release_jitter_seconds')),
            );
        } catch (Throwable $exception) {
            if (! $this->isProviderFailure($exception)) {
                throw $exception;
            }

            $this->retryOrFail($job, $exception);
        }
    }

    private function isProviderFailure(Throwable $exception): bool
    {
        if ($exception instanceof RateLimitedException
            || $exception instanceof ProviderConnectionException
            || $exception instanceof ProviderOverloadedException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response->serverError();
    }

    /**
     * Waits 10s, then 20s. Three failures fit inside the breaker's 60-second
     * window, so during an outage the third blocks the provider instead of
     * failing the job.
     */
    private function retryOrFail(object $job, Throwable $exception): void
    {
        $key = 'ai-job-failures:'.$job->job->uuid();
        $failures = RateLimiter::increment($key, 86400);

        if ($failures >= (int) config('ai.circuit_breaker.max_failures')) {
            RateLimiter::clear($key);
            $job->fail($exception);

            return;
        }

        $job->release(10 * (2 ** ($failures - 1)));
    }
}
