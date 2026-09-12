<?php

declare(strict_types=1);

namespace App\Routing;

enum WebRoute: string
{
    case CreateTicket = 'tickets.create';
    case StoreTicket = 'tickets.store';
    case ShowTicketCreated = 'tickets.created';
    case IndexTickets = 'tickets.index';
    case ShowTicket = 'tickets.show';
    case SendTicket = 'tickets.send';
    case DiscardTicket = 'tickets.discard';
}
