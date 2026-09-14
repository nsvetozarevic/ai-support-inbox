<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'message' => fake()->paragraph(),
            'reply' => null,
            'intent' => null,
            'status' => TicketStatus::New,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reply' => fake()->paragraph(),
            'status' => TicketStatus::Sent,
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Discarded,
        ]);
    }
}
