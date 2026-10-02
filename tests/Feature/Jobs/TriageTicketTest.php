<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Ai\Agents\TicketTriage;
use App\Ai\AiCircuitBreaker;
use App\Ai\Clients\TicketTriageClient;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Exceptions\AiCircuitOpenException;
use App\Jobs\TriageTicket;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class TriageTicketTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('retryableReleases')]
    public function test_retryable_provider_failure_is_released_with_increasing_delay(int $attempt, int $delay): void
    {
        TicketTriage::fake(
            fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), $attempt);

        $this->runThroughMiddleware($job);

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
        $exception = RateLimitedException::forProvider(Lab::OpenAI->value);

        TicketTriage::fake(
            fn () => throw $exception,
        )->preventStrayPrompts();

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), 3);

        $this->runThroughMiddleware($job);

        $job->assertFailedWith($exception);
        $job->assertNotReleased();
        $this->assertNull($job->ticket->fresh()->intent);
        $this->assertSame(AiPromptLogStatus::Failed, AiPromptLog::query()->sole()->status);
    }

    public function test_non_retryable_provider_failure_is_not_caught(): void
    {
        TicketTriage::fake(
            fn () => throw InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), 1);

        try {
            $this->runThroughMiddleware($job);
            $this->fail('Insufficient credits should not be caught by the job.');
        } catch (InsufficientCreditsException) {
            $job->assertNotReleased();
            $job->assertNotFailed();
            $this->assertNull($job->ticket->fresh()->intent);
        }
    }

    public function test_an_insufficient_credits_circuit_fails_the_job_without_prompting(): void
    {
        TicketTriage::fake()->preventStrayPrompts();

        (new AiCircuitBreaker)->recordFailure(
            AiModel::Gpt4oMini,
            InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        );

        $ticket = Ticket::factory()->create();

        try {
            TriageTicket::dispatch($ticket);
            $this->fail('An open circuit should fail the job.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertSame('AI circuit is open for '.Lab::OpenAI->value.' (insufficient credits).', $exception->getMessage());
            $this->assertNull($ticket->fresh()->intent);
            $this->assertSame(0, AiPromptLog::query()->count());
            TicketTriage::assertNeverPrompted();
        }
    }

    #[DataProvider('temporaryCircuits')]
    public function test_a_temporary_circuit_releases_the_job_until_the_block_ends_plus_jitter(Throwable $exception, int $delay): void
    {
        config(['ai.circuit_breaker.release_jitter_seconds' => 30]);

        $this->travelTo('2026-10-02 12:00:00');

        TicketTriage::fake()->preventStrayPrompts();

        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(AiModel::Gpt4oMini, $exception);
        }

        $job = $this->triageJobOnAttempt(Ticket::factory()->create(), 1);

        $this->runThroughMiddleware($job);

        $job->assertReleased();
        $this->assertGreaterThanOrEqual($delay, $job->job->releaseDelay);
        $this->assertLessThanOrEqual($delay + 30, $job->job->releaseDelay);
        $job->assertNotFailed();
        $this->assertNull($job->ticket->fresh()->intent);
        $this->assertSame(0, AiPromptLog::query()->count());
        TicketTriage::assertNeverPrompted();
    }

    /**
     * @return array<string, array{0: Throwable, 1: int}>
     */
    public static function temporaryCircuits(): array
    {
        return [
            'rate limit waits for the model block' => [RateLimitedException::forProvider(Lab::OpenAI->value), 60],
            'overload waits for the model block' => [ProviderOverloadedException::forProvider(Lab::OpenAI->value), 60],
            'connection waits for the provider block' => [ProviderConnectionException::forProvider(Lab::OpenAI->value), 900],
        ];
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

    private function runThroughMiddleware(TriageTicket $job): void
    {
        $job->middleware()[0]->handle($job, fn () => $job->handle($this->triageClient()));
    }

    private function triageJobOnAttempt(Ticket $ticket, int $attempt): TriageTicket
    {
        $job = (new TriageTicket($ticket))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        return $job;
    }

    private function triageClient(): TicketTriageClient
    {
        return new TicketTriageClient(new TicketTriage, new AiCircuitBreaker);
    }
}
