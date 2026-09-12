<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use App\Routing\WebRoute;
use Illuminate\Http\RedirectResponse;

class StoreTicketController extends Controller
{
    public function __invoke(StoreTicketRequest $request): RedirectResponse
    {
        Ticket::query()->create([
            ...$request->safe()->only(['name', 'email', 'message']),
            'status' => 'new',
        ]);

        return redirect()->route(WebRoute::ShowTicketCreated);
    }
}
