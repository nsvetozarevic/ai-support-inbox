<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HandbookDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'slug', 'body'])]
class HandbookDocument extends Model
{
    /** @use HasFactory<HandbookDocumentFactory> */
    use HasFactory;

    /**
     * @return HasMany<HandbookChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(HandbookChunk::class)->orderBy('position')->orderBy('id');
    }
}
