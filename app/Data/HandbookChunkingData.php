<?php

declare(strict_types=1);

namespace App\Data;

readonly class HandbookChunkingData
{
    /**
     * @param  list<ChunkSeparatorData>  $separators
     */
    public function __construct(
        public int $target,
        public int $window,
        public int $overlap,
        public array $separators,
    ) {}
}
