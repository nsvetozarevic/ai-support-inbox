<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HandbookChunk;
use App\Models\HandbookDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandbookChunk>
 */
class HandbookChunkFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (HandbookChunk $chunk): void {
            $chunk->body_hash ??= hash('sha256', $chunk->body);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);

        return [
            'handbook_document_id' => HandbookDocument::factory(),
            'key' => $slug.':0',
            'position' => 0,
            'body' => fake()->paragraph(),
        ];
    }
}
