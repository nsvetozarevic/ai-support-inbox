<?php

declare(strict_types=1);

use App\Enums\AiModel;

return [

    'catalog' => database_path('assets/handbook-documents.php'),

    /*
    | Must match the handbook_chunks.embedding vector column.
    */
    'embeddings' => [
        'model' => AiModel::TextEmbedding3Small->value,
        'dimensions' => 1536,
    ],

    /*
    | The provider is AiModel::RerankV35->provider() (Cohere).
    */
    'reranking' => [
        'model' => AiModel::RerankV35->value,
    ],

    /*
    | max_chunks caps word search, vector search, and the merge.
    | rerank_limit is how many of those chunks go to the draft later.
    |
    | rerank_min_relevance drops a Cohere score below this. Scores run from 0 to 1 and
    | depend on the question. 0.1 is deliberately low, so a useful chunk is kept
    | until this is measured. Measure it with real data: take representative
    | customer questions, pair each with the weakest chunk that should still
    | reach the draft, rerank those pairs, and use their average score here.
    */
    'retrieval' => [
        'max_chunks' => 20,
        'rerank_limit' => 8,
        'rrf_k' => 60,
        'vector_min_similarity' => 0.6,
        'rerank_min_relevance' => 0.1,
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
