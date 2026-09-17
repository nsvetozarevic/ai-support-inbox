<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Data\ChunkSeparatorData;
use App\Data\HandbookChunkingData;
use InvalidArgumentException;

class HandbookChunker
{
    private int $lid;

    public function __construct(private HandbookChunkingData $chunkingData)
    {
        $this->validate();

        $this->lid = $this->chunkingData->target + $this->chunkingData->window;
    }

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        $text = $this->normalize($text);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $chunkStart = 0;
        $length = mb_strlen($text);

        while ($chunkStart < $length) {
            if ($this->fitsInOneChunk($chunkStart, $length)) {
                return $this->appendSlice($chunks, $text, $chunkStart);
            }

            $cut = $this->cutOrLid($text, $chunkStart, $length);
            $chunks = $this->appendSlice($chunks, $text, $chunkStart, $cut);
            $chunkStart = $this->nextChunkStart($cut, $chunkStart);
        }

        return $chunks;
    }

    private function validate(): void
    {
        if ($this->chunkingData->target <= $this->chunkingData->window) {
            throw new InvalidArgumentException('Chunk target must be greater than window.');
        }

        if ($this->chunkingData->window < 0 || $this->chunkingData->overlap < 0) {
            throw new InvalidArgumentException('Chunk window and overlap must be zero or greater.');
        }

        if ($this->chunkingData->overlap >= $this->chunkingData->target) {
            throw new InvalidArgumentException('Chunk overlap must be smaller than target.');
        }
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    private function fitsInOneChunk(int $chunkStart, int $length): bool
    {
        return ($length - $chunkStart) <= $this->lid;
    }

    /**
     * @param  list<string>  $chunks
     * @return list<string>
     */
    private function appendSlice(array $chunks, string $text, int $from, ?int $until = null): array
    {
        $chunk = $until === null
            ? trim(mb_substr($text, $from))
            : trim(mb_substr($text, $from, $until - $from));

        if ($chunk !== '') {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function cutOrLid(string $text, int $chunkStart, int $length): int
    {
        $targetIndex = $chunkStart + $this->chunkingData->target;
        $windowStart = $targetIndex - $this->chunkingData->window;
        $windowEnd = min($length, $targetIndex + $this->chunkingData->window);

        $cut = $this->bestCut($text, $chunkStart, $windowStart, $windowEnd, $targetIndex);

        if ($cut === null || $cut <= $chunkStart) {
            return min($chunkStart + $this->lid, $length);
        }

        return $cut;
    }

    private function nextChunkStart(int $cut, int $chunkStart): int
    {
        $nextStart = $cut - $this->chunkingData->overlap;

        if ($nextStart <= $chunkStart) {
            return $cut;
        }

        return $nextStart;
    }

    private function bestCut(string $text, int $chunkStart, int $windowStart, int $windowEnd, int $targetIndex): ?int
    {
        if ($windowStart > $windowEnd) {
            return null;
        }

        foreach ($this->chunkingData->separators as $separator) {
            $cuts = $this->cutsFor($text, $separator, $chunkStart, $windowStart, $windowEnd);

            if ($cuts === []) {
                continue;
            }

            return $this->closestCut($cuts, $targetIndex);
        }

        return null;
    }

    /**
     * @param  list<int>  $cuts
     */
    private function closestCut(array $cuts, int $targetIndex): int
    {
        $closestCut = $cuts[0];
        $closestDistance = abs($closestCut - $targetIndex);

        foreach ($cuts as $cut) {
            $distance = abs($cut - $targetIndex);

            if ($distance < $closestDistance) {
                $closestCut = $cut;
                $closestDistance = $distance;
            }
        }

        return $closestCut;
    }

    /**
     * @return list<int>
     */
    private function cutsFor(string $text, ChunkSeparatorData $separator, int $chunkStart, int $windowStart, int $windowEnd): array
    {
        $cuts = [];
        $offset = $windowStart;
        $needle = $separator->value;

        while ($offset <= $windowEnd) {
            $found = mb_strpos($text, $needle, $offset);

            if ($found === false || $found > $windowEnd) {
                break;
            }

            $cut = $found + $separator->offset;

            if ($cut > $chunkStart && $cut <= $windowEnd) {
                $cuts[] = $cut;
            }

            $offset = $found + 1;
        }

        return $cuts;
    }
}
