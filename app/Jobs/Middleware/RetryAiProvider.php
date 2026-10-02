<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class RetryAiProvider
{
    /**
     * @param  Closure(object): void  $next
     */
    public function handle(object $job, Closure $next): void
    {
        try {
            $next($job);
        } catch (Throwable $exception) {
            if (! $this->shouldRetry($exception)) {
                throw $exception;
            }

            $this->retryOrFail($job, $exception);
        }
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof RateLimitedException
            || $exception instanceof ProviderConnectionException
            || $exception instanceof ProviderOverloadedException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response?->serverError();
    }

    private function retryOrFail(object $job, Throwable $exception): void
    {
        if ($job->attempts() >= $job->tries) {
            $job->fail($exception);

            return;
        }

        $job->release($this->retryDelay($job));
    }

    private function retryDelay(object $job): int
    {
        return 20 * (2 ** ($job->attempts() - 1));
    }
}
