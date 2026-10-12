<?php

declare(strict_types=1);

namespace App\Enums;

enum AiPromptLogStatus: string
{
    case Accepted = 'accepted';
    case NotCovered = 'not_covered';
    case Malformed = 'malformed';
    case Invalid = 'invalid';
    case Failed = 'failed';
}
