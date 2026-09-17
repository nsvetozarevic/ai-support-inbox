<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Data\ChunkSeparatorData;
use App\Data\HandbookChunkingData;
use App\Utilities\HandbookChunker;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HandbookChunkerTest extends TestCase
{
    public function test_short_text_returns_one_chunk(): void
    {
        $chunks = $this->chunker()->split('Short policy.');

        $this->assertSame(['Short policy.'], $chunks);
    }

    public function test_empty_text_returns_no_chunks(): void
    {
        $this->assertSame([], $this->chunker()->split('   '));
    }

    public function test_wall_of_text_never_exceeds_the_lid(): void
    {
        $text = str_repeat('a', 200);

        $chunks = $this->chunker()->split($text);

        $this->assertNotSame([], $chunks);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(70, mb_strlen($chunk));
        }
        $this->assertSame($text, implode('', $chunks));
    }

    public function test_keeps_a_paragraph_that_ends_just_after_the_target(): void
    {
        $paragraph = str_repeat('x', 55);
        $text = $paragraph."\n\n".str_repeat('y', 80);

        $chunks = $this->chunker()->split($text);

        $this->assertSame($paragraph, $chunks[0]);
    }

    public function test_cuts_at_the_paragraph_break_closest_to_the_target(): void
    {
        $early = str_repeat('a', 40);
        $near = str_repeat('b', 13);
        $text = $early."\n\n".$near."\n\n".str_repeat('c', 80);

        $chunks = $this->chunker()->split($text);

        $this->assertSame($early."\n\n".$near, $chunks[0]);
    }

    public function test_keeps_the_period_when_cutting_at_a_sentence(): void
    {
        $sentence = str_repeat('a', 48).'.';
        $text = $sentence.' '.str_repeat('b', 80);

        $chunks = $this->chunker()->split($text);

        $this->assertSame($sentence, $chunks[0]);
    }

    public function test_second_chunk_starts_with_the_overlap_from_the_first_chunk(): void
    {
        $text = str_repeat('x', 55)."\n\n".str_repeat('y', 80);

        $chunks = (new HandbookChunker($this->chunking(overlap: 10)))->split($text);

        $this->assertArrayHasKey(1, $chunks);
        $this->assertSame(str_repeat('x', 55), $chunks[0]);
        $this->assertSame(str_repeat('x', 10), mb_substr($chunks[1], 0, 10));
    }

    public function test_rejects_a_target_that_is_not_greater_than_the_window(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HandbookChunker($this->chunking(target: 20, window: 20, overlap: 0));
    }

    private function chunker(): HandbookChunker
    {
        return new HandbookChunker($this->chunking());
    }

    private function chunking(int $target = 50, int $window = 20, int $overlap = 0): HandbookChunkingData
    {
        return new HandbookChunkingData(
            target: $target,
            window: $window,
            overlap: $overlap,
            separators: $this->separators(),
        );
    }

    /**
     * @return list<ChunkSeparatorData>
     */
    private function separators(): array
    {
        return [
            new ChunkSeparatorData(value: "\n\n", offset: 0),
            new ChunkSeparatorData(value: "\n", offset: 0),
            new ChunkSeparatorData(value: '. ', offset: 1),
            new ChunkSeparatorData(value: '? ', offset: 1),
            new ChunkSeparatorData(value: '! ', offset: 1),
            new ChunkSeparatorData(value: ' ', offset: 0),
        ];
    }
}
