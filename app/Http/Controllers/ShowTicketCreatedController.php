<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ShowTicketCreatedController extends Controller
{
    public function __invoke(): View
    {
        return view('tickets.created');
    }
}
