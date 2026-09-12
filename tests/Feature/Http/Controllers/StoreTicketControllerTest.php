<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_submission_creates_a_new_ticket(): void
    {
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
