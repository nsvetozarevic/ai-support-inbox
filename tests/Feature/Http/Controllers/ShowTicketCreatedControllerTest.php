<?php

namespace Tests\Feature\Http\Controllers;

use App\Routing\WebRoute;
use Tests\TestCase;

class ShowTicketCreatedControllerTest extends TestCase
{
    public function test_created_page_shows_the_confirmation(): void
    {
        $this->get(route(WebRoute::ShowTicketCreated))
            ->assertSee('Message sent')
            ->assertSee('Thanks. Your message is in our inbox.');
    }
}
