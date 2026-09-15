# Production AI plan

Track what is in, what is out, and where we are. Update checkboxes as we land each slice.

**Product:** Northshore Supply contact form → ticket → staff inbox. The model proposes; PHP validates; a human sends. Never auto-send mail. Inbox stays public (no auth).

**Triage vs RAG:** Triage still runs on every new ticket and writes `intent`. It does **not** retrieve handbook chunks. RAG is for the drafter. Optional later: use `intent` as a metadata filter on chunks.

---

## Status

### Done

- [x] Contact form, ticket, inbox list/show, Send, Discard, mail
- [x] Queue worker, job retries for triage (`$tries` / `$maxExceptions`, backoff)
- [x] `TicketTriage` agent + `TicketTriageClient` + `TriageTicket` job
- [x] `AiPromptLog` on the show page
- [x] Handbook documents table + short seeder (unused by retrieval)
- [x] Compose Postgres with pgvector (unused by app code)

### Next (in order)

- [ ] Grow the handbook and split into chunks (stable ids; no model call yet)
- [ ] Embed + store (`vector` column, HNSW, `AsVector`). Reindex artisan command
- [ ] Hybrid retriever (full-text ∪ vectors → RRF). Golden tests: ticket text → expected slugs
- [ ] Rerank top candidates; log chunk ids on the prompt log
- [ ] Draft agent + client + job: retrieved text in, PHP grounding, write `reply` only if empty/valid. Prefill show page. Chain after triage
- [ ] Shared circuit breaker around every provider call
- [ ] Real README + real keys (`OPENAI_API_KEY`, `COHERE_API_KEY` if we rerank)

---

## In (not a hello-world RAG)

- Bigger handbook, **chunked**. Store embedding model + dimensions on the row
- **Reindex** command when pages change
- **Hybrid + RRF + rerank**, owned in Postgres. No OpenAI vector stores, no Scout
- **Retrieval trace** on `AiPromptLog` (chunk ids that went into the prompt)
- **PHP grounding:** draft may only assert policy that appears in retrieved chunk text; otherwise `Invalid`, do not fill `reply`
- Empty retrieval → no invented policy (ask for order number / refuse to draft)
- Do not overwrite a reply staff already edited
- **Shared circuit breaker** for triage, embeddings, rerank, draft. Open on credits / provider-down / 429 storm so jobs fail fast. Not Laravel Failover (a second paid model)
- **Golden retrieval tests** as the eval set (PHPUnit, not an ML platform)
- Prompt logs, retries, human Send (already there)

## Out

- Auth
- Agent `SimilaritySearch` tool-loops. PHP retrieves, then **one** draft prompt
- Query rewriting / HyDE
- Job `RateLimited` middleware (no real traffic)
- `prompt_versions` tables
- LangChain / Scout / Meilisearch
- Auto-send mail

---

## Hybrid and rerank

**Hybrid** is two first-stage searches, then a merge:

1. **Lexical** — Postgres full-text (`whereFullText`). Hits exact tokens: `#1001`, `Amex`, `14 days`
2. **Dense** — pgvector + `whereVectorSimilarTo`. Hits paraphrases: “I want my money back” → refunds page

Fuse with Reciprocal Rank Fusion (Laravel does not ship RRF). Own that in a small `HandbookRetriever`.

**Rerank** is the second stage: hybrid recalls ~30–50 candidates; a rerank model scores “does this chunk answer *this* ticket?”; keep the top 5–8 for the prompt. `laravel/ai` already has `Reranking` (config default: Cohere). Extra API key, not a new Composer package.

Hello world is `ORDER BY embedding <=> query LIMIT 5` and hope.

---

## Tests

PHPUnit uses sqlite `:memory:`. Real `vector` columns and Postgres full-text will not run there.

- HTTP tests stay on sqlite with fakes (`Embeddings::fake()`, `Reranking::fake()`, fake retriever)
- SQL hybrid tests run against Compose Postgres, or they are theatre
- Do not add tautological tests (factory definitions, `fake()` echoing the array you queued, `assertPrompted` on the same line that called `prompt()`)

---

## Keys

- `OPENAI_API_KEY` — triage, embeddings, draft
- `COHERE_API_KEY` — rerank, only if that stage is on
