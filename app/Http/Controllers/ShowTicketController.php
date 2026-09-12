<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\View\View;

class ShowTicketController extends Controller
{
    public function __invoke(Ticket $ticket): View
    {
        return view('tickets.show', [
            'ticket' => $ticket,
        ]);
    }
}
