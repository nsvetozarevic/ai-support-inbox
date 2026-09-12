<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\DiscardTicketRequest;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Http\RedirectResponse;

class DiscardTicketController extends Controller
{
    public function __invoke(DiscardTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update([
            'status' => TicketStatus::Discarded,
        ]);

        return redirect()->route(WebRoute::ShowTicket, $ticket);
    }
}
