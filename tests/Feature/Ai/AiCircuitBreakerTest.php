<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\AiCircuitBreaker;
use App\Enums\AiCircuitOpenReason;
use App\Enums\AiModel;
use App\Exceptions\AiCircuitOpenException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class AiCircuitBreakerTest extends TestCase
{
    public function test_insufficient_credits_blocks_every_model_on_that_provider(): void
    {
        $circuitBreaker = new AiCircuitBreaker;

        $circuitBreaker->recordFailure(
            AiModel::Gpt4oMini,
            InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        );

        try {
            $circuitBreaker->ensureAvailable(AiModel::TextEmbedding3Small);
            $this->fail('An empty OpenAI key should block embeddings too.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertSame(Lab::OpenAI->value, $exception->provider);
            $this->assertTrue($exception->providerWide);
            $this->assertSame(AiCircuitOpenReason::InsufficientCredits, $exception->reason);
            $this->assertSame('AI circuit is open for '.Lab::OpenAI->value.' (insufficient credits).', $exception->getMessage());
        }

        $circuitBreaker->ensureAvailable(AiModel::RerankV35);
    }

    public function test_an_openai_billing_error_blocks_every_model_on_that_provider(): void
    {
        $circuitBreaker = new AiCircuitBreaker;

        $circuitBreaker->recordFailure(AiModel::Gpt4oMini, $this->openAiRateLimit('insufficient_quota'));

        try {
            $circuitBreaker->ensureAvailable(AiModel::TextEmbedding3Small);
            $this->fail('An OpenAI billing limit should block embeddings too.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertSame(Lab::OpenAI->value, $exception->provider);
            $this->assertTrue($exception->providerWide);
            $this->assertSame(AiCircuitOpenReason::InsufficientCredits, $exception->reason);
        }

        $circuitBreaker->ensureAvailable(AiModel::RerankV35);
    }

    public function test_an_openai_rate_limit_leaves_the_model_available(): void
    {
        $this->expectNotToPerformAssertions();

        $circuitBreaker = new AiCircuitBreaker;

        $circuitBreaker->recordFailure(AiModel::Gpt4oMini, $this->openAiRateLimit('rate_limit_error'));

        $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
    }

    public function test_one_rate_limit_leaves_the_model_available(): void
    {
        $this->expectNotToPerformAssertions();

        $circuitBreaker = new AiCircuitBreaker;

        $circuitBreaker->recordFailure(
            AiModel::Gpt4oMini,
            RateLimitedException::forProvider(Lab::OpenAI->value),
        );

        $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
    }

    #[DataProvider('modelFailures')]
    public function test_a_burst_of_model_failures_opens_only_that_model(Throwable $exception, AiCircuitOpenReason $reason): void
    {
        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(AiModel::Gpt4oMini, $exception);
        }

        try {
            $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
            $this->fail('A burst of model failures should open that model.');
        } catch (AiCircuitOpenException $open) {
            $this->assertSame(AiModel::Gpt4oMini->value, $open->model);
            $this->assertFalse($open->providerWide);
            $this->assertSame($reason, $open->reason);
            $this->assertSame('AI circuit is open for '.Lab::OpenAI->value.' '.AiModel::Gpt4oMini->value." ({$reason->label()}).", $open->getMessage());
        }

        $circuitBreaker->ensureAvailable(AiModel::TextEmbedding3Small);
    }

    public function test_a_burst_of_connection_failures_opens_the_provider(): void
    {
        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(
                AiModel::Gpt4oMini,
                ProviderConnectionException::forProvider(Lab::OpenAI->value),
            );
        }

        try {
            $circuitBreaker->ensureAvailable(AiModel::TextEmbedding3Small);
            $this->fail('A burst of connection failures should open the provider.');
        } catch (AiCircuitOpenException $exception) {
            $this->assertSame(Lab::OpenAI->value, $exception->provider);
            $this->assertTrue($exception->providerWide);
            $this->assertSame(AiCircuitOpenReason::Connection, $exception->reason);
            $this->assertSame('AI circuit is open for '.Lab::OpenAI->value.' (connection).', $exception->getMessage());
        }

        $circuitBreaker->ensureAvailable(AiModel::RerankV35);
    }

    public function test_a_successful_call_closes_the_provider(): void
    {
        $this->expectNotToPerformAssertions();

        $circuitBreaker = new AiCircuitBreaker;

        $circuitBreaker->recordFailure(
            AiModel::TextEmbedding3Small,
            InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        );

        $circuitBreaker->recordSuccess(AiModel::TextEmbedding3Small);

        $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
    }

    public function test_calls_go_through_after_the_wait(): void
    {
        $this->expectNotToPerformAssertions();

        $circuitBreaker = new AiCircuitBreaker;

        $circuitBreaker->recordFailure(
            AiModel::Gpt4oMini,
            InsufficientCreditsException::forProvider(Lab::OpenAI->value),
        );

        $this->travel((int) config('ai.circuit_breaker.provider.open_seconds') + 1)->seconds();

        $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
        $circuitBreaker->ensureAvailable(AiModel::TextEmbedding3Small);
    }

    #[DataProvider('failuresAfterTheWait')]
    public function test_a_failure_after_the_wait_blocks_again(Throwable $exception, string $openSecondsKey, AiCircuitOpenReason $reason): void
    {
        $circuitBreaker = new AiCircuitBreaker;

        foreach (range(1, 3) as $ignored) {
            $circuitBreaker->recordFailure(AiModel::Gpt4oMini, $exception);
        }

        $this->travel((int) config($openSecondsKey) + 1)->seconds();

        $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
        $circuitBreaker->recordFailure(AiModel::Gpt4oMini, $exception);

        try {
            $circuitBreaker->ensureAvailable(AiModel::Gpt4oMini);
            $this->fail('A failure after the wait should block the next call.');
        } catch (AiCircuitOpenException $open) {
            $this->assertSame($reason, $open->reason);
        }
    }

    /**
     * @return array<string, array{0: Throwable, 1: string, 2: AiCircuitOpenReason}>
     */
    public static function failuresAfterTheWait(): array
    {
        return [
            'insufficient credits' => [InsufficientCreditsException::forProvider(Lab::OpenAI->value), 'ai.circuit_breaker.provider.open_seconds', AiCircuitOpenReason::InsufficientCredits],
            'connection' => [ProviderConnectionException::forProvider(Lab::OpenAI->value), 'ai.circuit_breaker.provider.open_seconds', AiCircuitOpenReason::Connection],
            'rate limit' => [RateLimitedException::forProvider(Lab::OpenAI->value), 'ai.circuit_breaker.model.open_seconds', AiCircuitOpenReason::RateLimit],
            'overloaded' => [ProviderOverloadedException::forProvider(Lab::OpenAI->value), 'ai.circuit_breaker.model.open_seconds', AiCircuitOpenReason::Overloaded],
            'server error' => [new RequestException(new Response(new PsrResponse(500))), 'ai.circuit_breaker.model.open_seconds', AiCircuitOpenReason::ServerError],
        ];
    }

    /**
     * @return array<string, array{0: Throwable, 1: AiCircuitOpenReason}>
     */
    public static function modelFailures(): array
    {
        return [
            'rate limit' => [RateLimitedException::forProvider(Lab::OpenAI->value), AiCircuitOpenReason::RateLimit],
            'overloaded' => [ProviderOverloadedException::forProvider(Lab::OpenAI->value), AiCircuitOpenReason::Overloaded],
            'server error' => [new RequestException(new Response(new PsrResponse(503))), AiCircuitOpenReason::ServerError],
        ];
    }

    private function openAiRateLimit(string $type): RateLimitedException
    {
        $response = new Response(new PsrResponse(
            429,
            [],
            json_encode(['error' => ['type' => $type]], JSON_THROW_ON_ERROR),
        ));

        return RateLimitedException::forProvider(
            Lab::OpenAI->value,
            0,
            new RequestException($response),
        );
    }
}
