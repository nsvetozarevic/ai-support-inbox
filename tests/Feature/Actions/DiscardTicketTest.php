<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\DiscardTicket;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscardTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_ticket_sent_by_another_request_after_authorization_stays_sent(): void
    {
        $ticket = Ticket::factory()->create([
            'reply' => 'The first request sent this.',
        ]);

        Ticket::query()->whereKey($ticket->id)->update([
            'status' => TicketStatus::Sent,
        ]);

        app(DiscardTicket::class)->handle($ticket);

        $ticket->refresh();

        $this->assertSame(TicketStatus::Sent, $ticket->status);
        $this->assertSame('The first request sent this.', $ticket->reply);
    }
}
