<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Ai\Agents\TicketDraft;
use App\Ai\Agents\TicketTriage;
use App\Enums\AiModel;
use App\Enums\AiPromptLogStatus;
use App\Enums\TicketIntent;
use App\Enums\TicketStatus;
use App\Jobs\DraftTicket;
use App\Jobs\TriageTicket;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Reranking;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_submission_creates_a_new_ticket(): void
    {
        TicketTriage::fake([
            ['intent' => TicketIntent::Shipping->value],
        ])->preventStrayPrompts();
        $this->fakeDraftRetrieval();

        $this->post(route(WebRoute::StoreTicket), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Where is my order #1001?',
        ])
            ->assertRedirect(route(WebRoute::ShowTicketCreated));

        $ticket = Ticket::query()->sole();

        $this->assertSame('Ada Lovelace', $ticket->name);
        $this->assertSame('ada@example.com', $ticket->email);
        $this->assertSame('Where is my order #1001?', $ticket->message);
        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertSame(TicketIntent::Shipping, $ticket->intent);
        TicketTriage::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Where is my order #1001?')
            && $prompt->model === AiModel::Gpt4oMini->value
            && $prompt->provider()->driver() === Lab::OpenAI->value);

        $log = AiPromptLog::query()->sole();
        $this->assertSame(TicketTriage::class, $log->agent);
        $this->assertSame((string) (new TicketTriage)->instructions(), $log->instructions);
        $this->assertSame('Where is my order #1001?', $log->input);
        $this->assertSame(['intent' => TicketIntent::Shipping->value], $log->response);
        $this->assertSame(AiPromptLogStatus::Accepted, $log->status);
        $this->assertNull($ticket->reply);
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('Where is my order #1001?'));
        TicketDraft::assertNeverPrompted();
    }

    public function test_valid_submission_queues_triage_and_draft(): void
    {
        Queue::fake();

        $this->post(route(WebRoute::StoreTicket), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Where is my order #1001?',
        ])
            ->assertRedirect(route(WebRoute::ShowTicketCreated));

        $ticket = Ticket::query()->sole();

        Queue::assertPushed(TriageTicket::class, fn (TriageTicket $job): bool => $job->ticket->is($ticket));
        Queue::assertPushed(DraftTicket::class, fn (DraftTicket $job): bool => $job->ticket->is($ticket));
    }

    public function test_invalid_model_intent_is_not_stored(): void
    {
        TicketTriage::fake([
            ['intent' => 'not-an-intent'],
        ])->preventStrayPrompts();
        $this->fakeDraftRetrieval();

        $this->post(route(WebRoute::StoreTicket), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Where is my order #1001?',
        ])
            ->assertRedirect(route(WebRoute::ShowTicketCreated));

        $this->assertNull(Ticket::query()->sole()->intent);

        $log = AiPromptLog::query()->sole();
        $this->assertSame('Where is my order #1001?', $log->input);
        $this->assertSame(['intent' => 'not-an-intent'], $log->response);
        $this->assertSame(AiPromptLogStatus::Invalid, $log->status);
    }

    public function test_one_visitor_is_limited_to_ten_submissions_an_hour(): void
    {
        RateLimiter::increment(md5('tickets'.'127.0.0.1'), 3600, 10);

        $this->post(route(WebRoute::StoreTicket))->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post(route(WebRoute::StoreTicket))
            ->assertSessionHasErrors('message');
    }

    private function fakeDraftRetrieval(): void
    {
        Embeddings::fake([[array_fill(0, 1536, 0.0)]]);
        Reranking::fake()->preventStrayRerankings();
        TicketDraft::fake()->preventStrayPrompts();
    }

    /**
     * @param  array{name: string, email: string, message: string}  $payload
     */
    #[DataProvider('invalidSubmissions')]
    public function test_invalid_submission_does_not_create_a_ticket(array $payload, string $field, string $message): void
    {
        $this->from(route(WebRoute::CreateTicket))
            ->post(route(WebRoute::StoreTicket), $payload)
            ->assertRedirect(route(WebRoute::CreateTicket))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('tickets', 0);
    }

    /**
     * @return array<string, array{0: array{name: string, email: string, message: string}, 1: string, 2: string}>
     */
    public static function invalidSubmissions(): array
    {
        return [
            'name required' => [
                ['name' => '', 'email' => 'ada@example.com', 'message' => 'Help'],
                'name',
                'The name field is required.',
            ],
            'email required' => [
                ['name' => 'Ada Lovelace', 'email' => '', 'message' => 'Help'],
                'email',
                'The email field is required.',
            ],
            'email format' => [
                ['name' => 'Ada Lovelace', 'email' => 'not-an-email', 'message' => 'Help'],
                'email',
                'The email field must be a valid email address.',
            ],
            'message required' => [
                ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => ''],
                'message',
                'The message field is required.',
            ],
        ];
    }
}
