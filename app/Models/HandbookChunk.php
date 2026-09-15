<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HandbookChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['handbook_document_id', 'key', 'position', 'body'])]
class HandbookChunk extends Model
{
    /** @use HasFactory<HandbookChunkFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<HandbookDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(HandbookDocument::class);
    }
}
