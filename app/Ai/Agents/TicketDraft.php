<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0)]
#[Timeout(60)]
class TicketDraft implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        You draft a reply for Northshore Supply staff.
        Use only the handbook excerpts in the user message.
        If the excerpts answer the customer, copy the supporting sentences into quotes.
        If they do not, leave quotes empty and say the handbook does not cover it.
        Do not invent shop policy.
        TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reply' => $schema->string()->required(),
            'quotes' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
