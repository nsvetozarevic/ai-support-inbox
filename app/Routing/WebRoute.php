<?php

namespace App\Routing;

enum WebRoute: string
{
    case CreateTicket = 'tickets.create';
    case StoreTicket = 'tickets.store';
    case ShowTicketCreated = 'tickets.created';
}
