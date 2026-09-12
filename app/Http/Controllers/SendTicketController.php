<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendTicket;
use App\Http\Requests\SendTicketRequest;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Http\RedirectResponse;

class SendTicketController extends Controller
{
    public function __invoke(SendTicketRequest $request, Ticket $ticket, SendTicket $sendTicket): RedirectResponse
    {
        $sendTicket->handle($ticket, $request->validated('reply'));

        return redirect()->route(WebRoute::ShowTicket, $ticket);
    }
}
