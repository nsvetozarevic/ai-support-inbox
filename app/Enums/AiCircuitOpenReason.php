<?php

declare(strict_types=1);

namespace App\Enums;

enum AiCircuitOpenReason: string
{
    case InsufficientCredits = 'insufficient_credits';
    case Connection = 'connection';
    case RateLimit = 'rate_limit';
    case Overloaded = 'overloaded';
    case ServerError = 'server_error';

    public function label(): string
    {
        return match ($this) {
            self::InsufficientCredits => 'insufficient credits',
            self::Connection => 'connection',
            self::RateLimit => 'rate limit',
            self::Overloaded => 'overloaded',
            self::ServerError => 'server error',
        };
    }
}
