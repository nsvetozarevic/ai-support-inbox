<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\AiPromptLogStatus;
use App\Enums\TicketIntent;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_missing_ticket_returns_404(): void
    {
        $this->get('/inbox/999')->assertNotFound();
    }
}
