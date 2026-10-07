<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\SendTicket;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_ticket_sent_by_another_request_after_authorization_is_not_mailed_again(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create();

        Ticket::query()->whereKey($ticket->id)->update([
            'reply' => 'The first request sent this.',
            'status' => TicketStatus::Sent,
        ]);

        app(SendTicket::class)->handle($ticket, 'The second request sent this.');

        $ticket->refresh();

        $this->assertSame(TicketStatus::Sent, $ticket->status);
        $this->assertSame('The first request sent this.', $ticket->reply);
        Mail::assertNothingSent();
    }
}
