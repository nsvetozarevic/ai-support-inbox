<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_mails_the_reply_and_marks_the_ticket_sent(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create([
            'email' => 'ada@example.com',
        ]);

        $this->post(route(WebRoute::SendTicket, $ticket), [
            'reply' => 'Your order shipped yesterday.',
        ])
            ->assertRedirect(route(WebRoute::ShowTicket, $ticket));

        $ticket->refresh();

        $this->assertSame('Your order shipped yesterday.', $ticket->reply);
        $this->assertSame(TicketStatus::Sent, $ticket->status);

        Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) use ($ticket): bool {
            return $mail->ticket->is($ticket)
                && $mail->hasTo('ada@example.com');
        });
    }

    public function test_send_rejects_an_empty_reply(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create();

        $this->from(route(WebRoute::ShowTicket, $ticket))
            ->post(route(WebRoute::SendTicket, $ticket), [
                'reply' => '',
            ])
            ->assertRedirect(route(WebRoute::ShowTicket, $ticket))
            ->assertSessionHasErrors(['reply' => 'The reply field is required.']);

        $this->assertSame(TicketStatus::New, $ticket->refresh()->status);
        Mail::assertNothingSent();
    }

    public function test_send_forbids_a_closed_ticket(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->sent()->create();

        $this->post(route(WebRoute::SendTicket, $ticket), [
            'reply' => 'Trying again.',
        ])->assertForbidden();

        Mail::assertNothingSent();
    }
}
