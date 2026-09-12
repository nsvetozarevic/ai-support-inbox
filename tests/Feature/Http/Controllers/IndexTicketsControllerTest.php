<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTicketsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_shows_empty_state_when_there_are_no_tickets(): void
    {
        $this->get(route(WebRoute::IndexTickets))
            ->assertSee('Inbox')
            ->assertSee('No tickets yet.');
    }

    public function test_inbox_lists_tickets_newest_first(): void
    {
        Ticket::factory()->create([
            'name' => 'Older Customer',
            'email' => 'old@example.com',
            'message' => 'First message',
            'created_at' => now()->subHour(),
        ]);
        Ticket::factory()->create([
            'name' => 'Newer Customer',
            'email' => 'new@example.com',
            'message' => 'Second message',
            'created_at' => now(),
        ]);

        $this->get(route(WebRoute::IndexTickets))
            ->assertSee('Newer Customer')
            ->assertSee('new@example.com')
            ->assertSee('Second message')
            ->assertSee('Older Customer')
            ->assertSeeInOrder(['Newer Customer', 'Older Customer']);
    }
}
