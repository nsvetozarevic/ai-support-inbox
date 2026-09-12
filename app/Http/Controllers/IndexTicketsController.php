<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\View\View;

class IndexTicketsController extends Controller
{
    public function __invoke(): View
    {
        return view('tickets.index', [
            'tickets' => Ticket::query()->latest()->get(),
        ]);
    }
}
