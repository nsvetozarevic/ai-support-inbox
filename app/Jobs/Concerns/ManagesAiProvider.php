<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RetryAiProvider;

trait ManagesAiProvider
{
    public int $tries = 3;

    public int $maxExceptions = 1;

    // Longer than the provider HTTP timeout (60s). The worker timeout is 90s.
    public int $timeout = 90;

    /**
     * @return list<RetryAiProvider>
     */
    public function middleware(): array
    {
        return [new RetryAiProvider];
    }
}
