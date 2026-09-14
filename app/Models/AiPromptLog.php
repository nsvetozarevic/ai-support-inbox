<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiPromptLogStatus;
use Database\Factories\AiPromptLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_id',
    'agent',
    'instructions',
    'input',
    'response',
    'status',
    'prompt_tokens',
    'completion_tokens',
    'error',
])]
class AiPromptLog extends Model
{
    /** @use HasFactory<AiPromptLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'array',
            'status' => AiPromptLogStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
