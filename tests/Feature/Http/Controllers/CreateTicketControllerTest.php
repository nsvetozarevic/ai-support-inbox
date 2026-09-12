<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Routing\WebRoute;
use Tests\TestCase;

class CreateTicketControllerTest extends TestCase
{
    public function test_create_ticket_page_renders_the_form(): void
    {
        $this->get(route(WebRoute::CreateTicket))
            ->assertSee('Contact')
            ->assertSee('Send message');
    }
}
