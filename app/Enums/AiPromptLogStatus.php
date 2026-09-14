<?php

declare(strict_types=1);

namespace App\Enums;

enum AiPromptLogStatus: string
{
    case Accepted = 'accepted';
    case Invalid = 'invalid';
    case Failed = 'failed';
}
