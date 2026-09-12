<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketReplyMail extends Mailable
{
    public function __construct(public Ticket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: your support request',
            to: [new Address($this->ticket->email, $this->ticket->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ticket-reply',
        );
    }
}
