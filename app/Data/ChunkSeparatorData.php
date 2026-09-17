<?php

declare(strict_types=1);

namespace App\Data;

readonly class ChunkSeparatorData
{
    public function __construct(
        public string $value,
        public int $offset,
    ) {}
}
