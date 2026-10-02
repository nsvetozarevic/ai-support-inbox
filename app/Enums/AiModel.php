<?php

declare(strict_types=1);

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

enum AiModel: string
{
    case Gpt4oMini = 'gpt-4o-mini';
    case TextEmbedding3Small = 'text-embedding-3-small';
    case RerankV35 = 'rerank-v3.5';

    public function provider(): Lab
    {
        return match ($this) {
            self::Gpt4oMini, self::TextEmbedding3Small => Lab::OpenAI,
            self::RerankV35 => Lab::Cohere,
        };
    }
}
