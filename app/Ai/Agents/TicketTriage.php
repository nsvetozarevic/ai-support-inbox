<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\TicketIntent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
#[Temperature(0)]
#[Timeout(60)]
class TicketTriage implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        You triage Northshore Supply support tickets.
        Choose exactly one intent from the allowed enum.
        If the message is unclear or does not fit refund, shipping, or stock, use other.
        Do not invent shop policy. Do not write a reply.
        TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()->enum(
                array_column(TicketIntent::cases(), 'value'),
            )->required(),
        ];
    }
}
