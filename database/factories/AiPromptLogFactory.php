<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Ai\Agents\TicketTriage;
use App\Enums\AiPromptLogStatus;
use App\Enums\TicketIntent;
use App\Models\AiPromptLog;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptLog>
 */
class AiPromptLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'agent' => TicketTriage::class,
            'instructions' => (string) (new TicketTriage)->instructions(),
            'input' => fake()->paragraph(),
            'response' => ['intent' => TicketIntent::Shipping->value],
            'status' => AiPromptLogStatus::Accepted,
            'prompt_tokens' => 12,
            'completion_tokens' => 4,
            'error' => null,
        ];
    }
}
