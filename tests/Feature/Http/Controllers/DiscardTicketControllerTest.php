<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DiscardTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_discard_marks_the_ticket_discarded_without_mail(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create();

        $this->post(route(WebRoute::DiscardTicket, $ticket))
            ->assertRedirect(route(WebRoute::ShowTicket, $ticket));

        $this->assertSame(TicketStatus::Discarded, $ticket->refresh()->status);
        Mail::assertNothingSent();
    }

    public function test_discard_forbids_a_closed_ticket(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->discarded()->create();

        $this->post(route(WebRoute::DiscardTicket, $ticket))->assertForbidden();

        Mail::assertNothingSent();
    }
}
