<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case Sent = 'sent';
    case Discarded = 'discarded';
}
