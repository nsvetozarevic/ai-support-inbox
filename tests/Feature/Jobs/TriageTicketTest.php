<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Ai\Agents\TicketTriage;
use App\Ai\AiCircuitBreaker;
use App\Ai\Clients\TicketTriageClient;
use App\Enums\AiCircuitOpenReason;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Exceptions\AiCircuitOpenException;
use App\Jobs\TriageTicket;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TriageTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_call_is_released_for_another_try(): void
    {
        TicketTriage::fake(
            fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = $this->triageJob(Ticket::factory()->create());

        $this->runThroughMiddleware($job);

        $job->assertReleased(10);
        $job->assertNotFailed();
        $this->assertNull($job->ticket->fresh()->intent);

        $log = AiPromptLog::query()->sole();
        $this->assertSame(AiPromptLogStatus::Failed, $log->status);
        $this->assertSame((string) (new TicketTriage)->instructions(), $log->instructions);
        $this->assertSame($job->ticket->message, $log->input);
        $this->assertNotNull($log->error);
    }

    public function test_the_job_fails_after_max_failures_failed_calls(): void
    {
        config(['ai.circuit_breaker.max_failures' => 3]);

        $exception = RateLimitedException::forProvider(Lab::OpenAI->value);

        TicketTriage::fake(fn () => throw $exception)->preventStrayPrompts();

        $job = $this->triageJob(Ticket::factory()->create());
        $circuitBreaker = new AiCircuitBreaker;

        $this->runThroughMiddleware($job);
        $job->assertReleased(10);

        $circuitBreaker->recordSuccess(AiModel::Gpt4oMini);
        $this->runThroughMiddleware($job);
        $job->assertReleased(20);

        $circuitBreaker->recordSuccess(AiModel::Gpt4oMini);
        $this->runThroughMiddleware($job);
        $job->assertFailedWith($exception);
    }

    public function test_a_failed_call_that_blocks_the_model_waits_without_counting(): void
    {
        config(['ai.circuit_breaker.release_jitter_seconds' => 30]);

        $this->travelTo('2026-10-02 12:00:00');

        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 2) as $ignored) {
            $circuitBreaker->recordFailure(AiModel::Gpt4oMini, RateLimitedException::forProvider(Lab::OpenAI->value));
        }

        TicketTriage::fake(
            fn () => throw RateLimitedException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = $this->triageJob(Ticket::factory()->create());

        $this->runThroughMiddleware($job);

        $job->assertReleased();
        $this->assertGreaterThanOrEqual(60, $job->job->releaseDelay);
        $this->assertLessThanOrEqual(90, $job->job->releaseDelay);
        $job->assertNotFailed();
        $this->assertSame(AiPromptLogStatus::Failed, AiPromptLog::query()->sole()->status);
    }

    public function test_insufficient_credits_are_rethrown_so_the_job_fails_at_once(): void
    {
        TicketTriage::fake(
            fn () => throw InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        )->preventStrayPrompts();

        $job = $this->triageJob(Ticket::factory()->create());

        try {
            $this->runThroughMiddleware($job);
            $this->fail('Insufficient credits should not be retried.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertSame(AiCircuitOpenReason::InsufficientCredits, $exception->reason);
        }

        $job->assertNotReleased();
        $this->assertNull($job->ticket->fresh()->intent);
    }

    public function test_an_error_that_is_not_a_provider_failure_is_rethrown(): void
    {
        TicketTriage::fake(fn () => throw new RuntimeException('Unexpected.'))->preventStrayPrompts();

        $job = $this->triageJob(Ticket::factory()->create());

        try {
            $this->runThroughMiddleware($job);
            $this->fail('An unknown error should not be retried.');
        } catch (RuntimeException) {
            $job->assertNotReleased();
            $job->assertNotFailed();
            $this->assertNull($job->ticket->fresh()->intent);
        }
    }

    public function test_an_insufficient_credits_circuit_is_rethrown_without_prompting(): void
    {
        TicketTriage::fake()->preventStrayPrompts();

        (new AiCircuitBreaker)->recordFailure(
            AiModel::Gpt4oMini,
            InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        );

        $job = $this->triageJob(Ticket::factory()->create());

        try {
            $this->runThroughMiddleware($job);
            $this->fail('An insufficient credits circuit should not be retried.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertSame('AI circuit is open for '.Lab::OpenAI->value.' (insufficient credits).', $exception->getMessage());
        }

        $job->assertNotReleased();
        $this->assertSame(0, AiPromptLog::query()->count());
        TicketTriage::assertNeverPrompted();
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

        $job = $this->triageJob(Ticket::factory()->create());

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
            'server error waits for the model block' => [new RequestException(new Response(new PsrResponse(500))), 60],
            'connection waits for the provider block' => [ProviderConnectionException::forProvider(Lab::OpenAI->value), 900],
        ];
    }

    public function test_the_job_deadline_comes_from_config(): void
    {
        config(['ai.job_deadline_hours' => 2]);

        $this->travelTo('2026-10-02 12:00:00');

        $this->assertSame(
            now()->addHours(2)->getTimestamp(),
            $this->triageJob(Ticket::factory()->create())->retryUntil()->getTimestamp(),
        );
    }

    #[DataProvider('closedTickets')]
    public function test_a_closed_ticket_is_skipped_without_prompting(string $state): void
    {
        TicketTriage::fake()->preventStrayPrompts();

        $job = $this->triageJob(Ticket::factory()->{$state}()->create());

        $this->runThroughMiddleware($job);

        $job->assertNotReleased();
        $job->assertNotFailed();
        $this->assertNull($job->ticket->fresh()->intent);
        $this->assertSame(0, AiPromptLog::query()->count());
        TicketTriage::assertNeverPrompted();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function closedTickets(): array
    {
        return [
            'sent' => ['sent'],
            'discarded' => ['discarded'],
        ];
    }

    private function runThroughMiddleware(TriageTicket $job): void
    {
        $job->middleware()[0]->handle($job, fn () => $job->handle($this->triageClient()));
    }

    private function triageJob(Ticket $ticket): TriageTicket
    {
        return (new TriageTicket($ticket))->withFakeQueueInteractions();
    }

    private function triageClient(): TicketTriageClient
    {
        return new TicketTriageClient(new TicketTriage, new AiCircuitBreaker);
    }
}
