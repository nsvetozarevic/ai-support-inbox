<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Ai\Agents\TicketTriage;
use App\Ai\Clients\TicketTriageClient;
use App\Enums\AiPromptLogStatus;
use App\Jobs\TriageTicket;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\RateLimitedException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TriageTicketTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('retryableReleases')]
    public function test_retryable_provider_failure_is_released_with_increasing_delay(int $attempt, int $delay): void
    {
        TicketTriage::fake(
            fn () => throw RateLimitedException::forProvider('openai'),
        )->preventStrayPrompts();

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), $attempt);

        $job->handle($this->triageClient());

        $job->assertReleased($delay);
        $job->assertNotFailed();
        $this->assertNull($job->ticket->fresh()->intent);

        $log = AiPromptLog::query()->sole();
        $this->assertSame(AiPromptLogStatus::Failed, $log->status);
        $this->assertSame((string) (new TicketTriage)->instructions(), $log->instructions);
        $this->assertSame($job->ticket->message, $log->input);
        $this->assertNotNull($log->error);
    }

    public function test_retryable_provider_failure_on_last_attempt_fails_with_that_exception(): void
    {
        $exception = RateLimitedException::forProvider('openai');

        TicketTriage::fake(
            fn () => throw $exception,
        )->preventStrayPrompts();

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), 3);

        $job->handle($this->triageClient());

        $job->assertFailedWith($exception);
        $job->assertNotReleased();
        $this->assertNull($job->ticket->fresh()->intent);
        $this->assertSame(AiPromptLogStatus::Failed, AiPromptLog::query()->sole()->status);
    }

    public function test_non_retryable_provider_failure_is_not_caught(): void
    {
        TicketTriage::fake(
            fn () => throw InsufficientCreditsException::forProvider('openai'),
        )->preventStrayPrompts();

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), 1);

        try {
            $job->handle($this->triageClient());
            $this->fail('Insufficient credits should not be caught by the job.');
        } catch (InsufficientCreditsException) {
            $job->assertNotReleased();
            $job->assertNotFailed();
            $this->assertNull($job->ticket->fresh()->intent);
        }
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function retryableReleases(): array
    {
        return [
            'first attempt waits 20 seconds' => [1, 20],
            'second attempt waits 40 seconds' => [2, 40],
        ];
    }

    private function triageJobOnAttempt(Ticket $ticket, int $attempt): TriageTicket
    {
        $job = (new TriageTicket($ticket))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        return $job;
    }

    private function triageClient(): TicketTriageClient
    {
        return new TicketTriageClient(new TicketTriage);
    }
}
