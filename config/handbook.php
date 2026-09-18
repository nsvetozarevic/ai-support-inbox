<?php

declare(strict_types=1);

return [

    'catalog' => database_path('assets/handbook-documents.php'),

    /*
    | Must match the handbook_chunks.embedding vector column.
    */
    'embeddings' => [
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
    ],

    /*
    | Characters, not tokens. lid is target + window (not configurable).
    */
    'chunking' => [
        'target' => 1200,
        'window' => 120,
        'overlap' => 80,
    ],

    /*
    | First needle that hits in the window wins. Reorder with caution.
    |
    | "value" is the exact bytes we search for. Double quotes make \n a real
    | newline ("\n\n" is a blank line). Single quotes do not: '\n\n' is a
    | backslash and the letter n. Use single quotes only when the needle has
    | no escapes ('. ', ' ').
    |
    | "label" is for humans reading this file. It is not used at runtime.
    | "offset" is 1 to keep the first character of the needle in this chunk
    | (the period in ". "), otherwise 0.
    */
    'separators' => [
        [
            'label' => 'Paragraph break',
            'value' => "\n\n",
            'offset' => 0,
        ],
        [
            'label' => 'Line break',
            'value' => "\n",
            'offset' => 0,
        ],
        [
            'label' => 'Sentence period',
            'value' => '. ',
            'offset' => 1,
        ],
        [
            'label' => 'Sentence question',
            'value' => '? ',
            'offset' => 1,
        ],
        [
            'label' => 'Sentence exclaim',
            'value' => '! ',
            'offset' => 1,
        ],
        [
            'label' => 'Word',
            'value' => ' ',
            'offset' => 0,
        ],
    ],

];
