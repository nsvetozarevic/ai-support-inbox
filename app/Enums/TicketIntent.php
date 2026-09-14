<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketIntent: string
{
    case Refund = 'refund';
    case Shipping = 'shipping';
    case Stock = 'stock';
    case Other = 'other';
}
