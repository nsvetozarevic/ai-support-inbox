<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Ai\Agents\TicketDraft;
use App\Ai\Agents\TicketTriage;
use App\Enums\AiPromptLogStatus;
use App\Enums\TicketIntent;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShowTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_renders_the_ticket(): void
    {
        $ticket = Ticket::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Where is my order #1001?',
            'intent' => TicketIntent::Refund,
        ]);

        $log = AiPromptLog::factory()->create([
            'ticket_id' => $ticket->id,
            'instructions' => 'Triage Northshore Supply tickets only.',
            'input' => 'Where is my order #1001?',
            'status' => AiPromptLogStatus::Accepted,
        ]);

        $this->get(route(WebRoute::ShowTicket, $ticket))
            ->assertSee('Ada Lovelace')
            ->assertSee('ada@example.com')
            ->assertSee('Where is my order #1001?')
            ->assertSee('refund')
            ->assertSee('Send')
            ->assertSee('Discard')
            ->assertSee('Model log')
            ->assertSee($log->instructions)
            ->assertSee($log->input)
            ->assertSee('accepted');
    }

    public function test_show_page_prefills_the_suggested_reply(): void
    {
        $ticket = Ticket::factory()->create([
            'reply' => 'We do not take Amex.',
        ]);

        $this->get(route(WebRoute::ShowTicket, $ticket))
            ->assertSee('We do not take Amex.');
    }

    public function test_show_page_lists_the_reply_quotes_above_send(): void
    {
        $ticket = Ticket::factory()->create([
            'reply' => 'We do not take Amex.',
            'reply_quotes' => ['No Amex. Visa and Mastercard only.'],
        ]);

        $this->get(route(WebRoute::ShowTicket, $ticket))
            ->assertSeeInOrder([
                'From the handbook',
                'No Amex. Visa and Mastercard only.',
                'Send',
            ])
            ->assertSee('data-stale="0"', false);
    }

    public function test_show_page_hides_handbook_quotes_when_the_reply_was_rewritten(): void
    {
        $ticket = Ticket::factory()->create([
            'reply' => 'We do not take Amex.',
            'reply_quotes' => ['No Amex. Visa and Mastercard only.'],
        ]);

        $this->followingRedirects()
            ->from(route(WebRoute::ShowTicket, $ticket))
            ->post(route(WebRoute::SendTicket, $ticket), [
                'reply' => '',
            ])
            ->assertSee('data-stale="1"', false);
    }

    #[DataProvider('unfinishedDrafts')]
    public function test_show_page_explains_why_the_draft_was_not_kept(AiPromptLogStatus $status, string $notice): void
    {
        $ticket = Ticket::factory()->create();

        AiPromptLog::factory()->create([
            'ticket_id' => $ticket->id,
            'agent' => TicketDraft::class,
            'status' => $status,
        ]);

        $this->get(route(WebRoute::ShowTicket, $ticket))
            ->assertSeeInOrder([$notice, 'Send']);
    }

    /**
     * @return array<string, array{0: AiPromptLogStatus, 1: string}>
     */
    public static function unfinishedDrafts(): array
    {
        return [
            'not covered' => [AiPromptLogStatus::NotCovered, "The handbook doesn't cover this. Write the reply yourself."],
            'badly formed' => [AiPromptLogStatus::Malformed, 'The draft was rejected because it was badly formed.'],
            'quotes not in the handbook' => [AiPromptLogStatus::Invalid, 'The draft was rejected because its quotes were not in the handbook.'],
            'failed' => [AiPromptLogStatus::Failed, 'The last draft attempt failed.'],
        ];
    }

    public function test_show_page_uses_the_latest_draft_log(): void
    {
        $ticket = Ticket::factory()->create();

        $this->travelTo(now()->subMinute());

        AiPromptLog::factory()->create([
            'ticket_id' => $ticket->id,
            'agent' => TicketDraft::class,
            'status' => AiPromptLogStatus::Failed,
        ]);

        $this->travelBack();

        AiPromptLog::factory()->create([
            'ticket_id' => $ticket->id,
            'agent' => TicketDraft::class,
            'status' => AiPromptLogStatus::Accepted,
        ]);

        $this->get(route(WebRoute::ShowTicket, $ticket))
            ->assertDontSee('The last draft attempt failed.');
    }

    public function test_show_page_does_not_explain_a_triage_log_as_a_draft(): void
    {
        $ticket = Ticket::factory()->create();

        AiPromptLog::factory()->create([
            'ticket_id' => $ticket->id,
            'agent' => TicketTriage::class,
            'status' => AiPromptLogStatus::Invalid,
        ]);

        $this->get(route(WebRoute::ShowTicket, $ticket))
            ->assertDontSee('The draft was rejected because it was badly formed.')
            ->assertDontSee('The draft was rejected because its quotes were not in the handbook.');
    }

    public function test_missing_ticket_returns_404(): void
    {
        $this->get('/inbox/999')->assertNotFound();
    }
}
