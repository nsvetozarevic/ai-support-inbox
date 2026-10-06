<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
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

    public function test_a_mail_failure_leaves_the_ticket_open(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('The mail server refused the message.'));

        $ticket = Ticket::factory()->create([
            'reply' => 'Draft reply.',
            'reply_quotes' => ['No Amex. Visa and Mastercard only.'],
        ]);

        $this->post(route(WebRoute::SendTicket, $ticket), [
            'reply' => 'Your order shipped yesterday.',
        ])->assertStatus(500);

        $ticket->refresh();

        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertSame('Draft reply.', $ticket->reply);
        $this->assertSame(['No Amex. Visa and Mastercard only.'], $ticket->reply_quotes);
    }

    public function test_send_keeps_quotes_when_the_reply_is_unchanged(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create([
            'reply' => 'Your order shipped yesterday.',
            'reply_quotes' => ['Every paid order has a number.'],
        ]);

        $this->post(route(WebRoute::SendTicket, $ticket), [
            'reply' => 'Your order shipped yesterday.',
        ]);

        $this->assertSame(['Every paid order has a number.'], $ticket->fresh()->reply_quotes);
    }

    public function test_send_drops_quotes_when_the_reply_was_rewritten(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create([
            'reply' => 'We do not take Amex.',
            'reply_quotes' => ['No Amex. Visa and Mastercard only.'],
        ]);

        $this->post(route(WebRoute::SendTicket, $ticket), [
            'reply' => 'Your order shipped yesterday.',
        ]);

        $ticket->refresh();

        $this->assertSame(TicketStatus::Sent, $ticket->status);
        $this->assertSame('Your order shipped yesterday.', $ticket->reply);
        $this->assertNull($ticket->reply_quotes);
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
