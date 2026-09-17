# Handbook chunking

Why we split documents this way. Implementation is `App\Utilities\HandbookChunker`. Sizes in **characters** for v1 (a stand-in for tokens).

## Why we chunk

A document is too big and too mixed to paste into the model every time. We cut it into slices, later retrieve a few, then draft. That is RAG.

Writers are **not** required to use blank lines or any other format. A Word dump or a PDF extract must still split.

## What we rejected

- **Paragraph-only split** — hello world. Breaks when someone pastes a wall of text.
- **Hard cut every N characters** — cuts mid-sentence.
- **LLM / “semantic” split on v1** — extra model in ingest, harder to test. At our scale it is not about money. Recursive + window is deterministic. Sentence-similarity cuts can wait until goldens say the cuts are the problem.

LangChain is a Python toolkit that already ships a recursive splitter. We do the same idea in PHP. We do not add LangChain to this Laravel app.

## Three numbers (plus overlap)

They mean different things:

| Number | Role |
|--------|------|
| **Lid** | `target + window`. Not configured. Longest one slice can be. |
| **Target** | Where we *like* a slice to sit. Must be **greater than** window. |
| **Window** | How far we look **both ways** from the target for a nicer cut. |
| **Overlap** | Copy the tail of chunk *n* onto chunk *n+1*. Must be smaller than target. Not added into lid. |

`lid` = `target + window` (here 1320). That is the longest one slice can be: a nice cut at the far edge of the window, or a hard cut if there is no boundary.

**Overlap is not in that sum.** It does not make chunk 1 longer. After we cut, chunk 2 *starts* 80 characters earlier so those 80 characters appear in both slices. Each slice is still ≤ lid.

Textbook “look only backward from 400” is this design with window-over = 0. We did not want that: a paragraph that ends 5 characters after the target should be kept.

Start **symmetric** (e.g. ±10%). Asymmetric +40/−20 is allowed later if goldens say we prefer long leftovers over short ones. Do not tune that first.

## How a cut is chosen

Walk the text. Around `start + target`, open `[target − window, target + window]`, clipped so the chunk never exceeds the lid.

In that band, look for a boundary, strongest first:

1. Blank line
2. Newline
3. End of sentence (`.?!`)
4. Space (word)

If several of the same kind exist, pick the **closest to the target** (before or after). If none, cut at the lid.

No model. “Best” is that list, not GPT.

## Identity

The catalog is `database/assets/handbook-documents.php`. `php artisan handbook:resync` (and the seeder) upserts each page by **slug**, rebuilds that page’s chunks, then deletes documents whose slug is no longer in the file.

Slug is the stable document key. Chunks are a derived view of the current body. A change anywhere in the page can move every cut. New slices get new row ids. `position` is only order in this split. `key` (`slug:position`) is for logs, not a forever id.

Do not cite a chunk `id` across resyncs. A retrieval trace is “what we pulled for this prompt”.

## Who writes the code

We do: a small PHP class + `config/handbook.php`. Not an embedding call, not LangChain.
