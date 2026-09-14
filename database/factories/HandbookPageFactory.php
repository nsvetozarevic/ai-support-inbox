<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HandbookPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandbookPage>
 */
class HandbookPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(3),
            'body' => fake()->paragraphs(3, true),
        ];
    }
}
