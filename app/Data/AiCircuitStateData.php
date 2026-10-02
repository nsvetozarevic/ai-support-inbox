<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AiCircuitOpenReason;

readonly class AiCircuitStateData
{
    public function __construct(
        public int $until,
        public AiCircuitOpenReason $reason,
    ) {}
}
