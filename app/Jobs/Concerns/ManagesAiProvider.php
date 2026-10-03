<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RetryAiProvider;
use DateTimeInterface;

trait ManagesAiProvider
{
    // No try limit: waits on a blocked provider do not count. RetryAiProvider counts failed calls.
    // retryUntil() is the safety net: once the deadline passes, the worker fails the job
    // the next time it picks it up, whatever the cause.
    public int $tries = 0;

    // Any exception RetryAiProvider does not handle fails the job at once. This also
    // fails a job the worker kills on timeout. Without it, a timed-out job would come back forever.
    public int $maxExceptions = 1;

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours((int) config('ai.job_deadline_hours'));
    }

    /**
     * @return list<RetryAiProvider>
     */
    public function middleware(): array
    {
        return [new RetryAiProvider];
    }
}
