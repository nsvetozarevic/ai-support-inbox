<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HandbookPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'body'])]
class HandbookPage extends Model
{
    /** @use HasFactory<HandbookPageFactory> */
    use HasFactory;
}
