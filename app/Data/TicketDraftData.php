<?php

declare(strict_types=1);

namespace App\Data;

use App\Exceptions\InvalidTicketDraftException;

readonly class TicketDraftData
{
    /**
     * @param  list<string>  $quotes
     */
    public function __construct(
        public string $reply,
        public array $quotes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        $reply = $payload['reply'] ?? null;
        $quotes = $payload['quotes'] ?? null;

        if (! is_string($reply) || ! is_array($quotes)) {
            throw new InvalidTicketDraftException('The model draft is not a reply and a list of quotes.');
        }

        foreach ($quotes as $quote) {
            if (! is_string($quote)) {
                throw new InvalidTicketDraftException('The model draft is not a reply and a list of quotes.');
            }
        }

        return new self($reply, array_values($quotes));
    }

    /**
     * @return array{reply: string, quotes: list<string>}
     */
    public function toArray(): array
    {
        return [
            'reply' => $this->reply,
            'quotes' => $this->quotes,
        ];
    }
}
