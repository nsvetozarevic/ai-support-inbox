<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Clients\TicketTriageClient;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class TriageTicket implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $maxExceptions = 1;

    // Longer than the agent HTTP timeout (60s) so a slow call fails as an exception, not a worker kill.
    public int $timeout = 90;

    public function __construct(public Ticket $ticket) {}

    public function handle(TicketTriageClient $client): void
    {
        try {
            $intent = $client->triage($this->ticket);
        } catch (Throwable $exception) {
            if (! $this->shouldRetry($exception)) {
                throw $exception;
            }

            $this->retryOrFail($exception);

            return;
        }

        if ($intent === null) {
            return;
        }

        $this->ticket->update([
            'intent' => $intent,
        ]);
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

    private function retryOrFail(Throwable $exception): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->fail($exception);

            return;
        }

        $this->release($this->retryDelay());
    }

    private function retryDelay(): int
    {
        return 20 * (2 ** ($this->attempts() - 1));
    }
}
