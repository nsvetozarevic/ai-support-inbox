<?php

declare(strict_types=1);

namespace App\Utilities;

use Illuminate\Support\Facades\Validator;

class DocumentLoader
{
    /**
     * @return list<array{title: string, slug: string, body: string}>
     */
    public function documents(): array
    {
        $documents = require config('handbook.catalog');

        Validator::make(
            ['documents' => $documents],
            [
                'documents' => ['required', 'array', 'min:1'],
                'documents.*' => ['required', 'array'],
                'documents.*.title' => ['required', 'string'],
                'documents.*.slug' => ['required', 'string', 'distinct'],
                'documents.*.body' => ['required', 'string'],
            ],
        )->validate();

        /** @var list<array{title: string, slug: string, body: string}> $documents */
        return $documents;
    }
}
