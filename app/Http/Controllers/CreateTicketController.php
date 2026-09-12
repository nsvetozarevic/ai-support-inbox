<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CreateTicketController extends Controller
{
    public function __invoke(): View
    {
        return view('tickets.create');
    }
}
