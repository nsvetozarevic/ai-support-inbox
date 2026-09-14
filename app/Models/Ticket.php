<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketIntent;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'message', 'reply', 'intent', 'status'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => TicketStatus::New->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'intent' => TicketIntent::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === TicketStatus::New;
    }

    /**
     * @return HasMany<AiPromptLog, $this>
     */
    public function aiPromptLogs(): HasMany
    {
        return $this->hasMany(AiPromptLog::class);
    }
}
