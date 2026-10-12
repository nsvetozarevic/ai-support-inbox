# AI Support Inbox

[![Tests](https://github.com/nsvetozarevic/ai-support-inbox/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/nsvetozarevic/ai-support-inbox/actions/workflows/tests.yml)

Support inbox for a made-up shop, Northshore Supply, built with Laravel and the Laravel AI SDK. A customer writes in and the app opens a ticket. Queue workers classify it and draft a reply from the shop handbook. Staff edit the draft, send it, or discard the ticket.

The model proposes. PHP checks the draft. A person sends the mail.

This is a portfolio demo, not a production app. It shows the AI side of a support inbox: retrieval, grounding, and handling provider failures. It has no authentication, so do not deploy it as is. See [Not included](#not-included).

## What it does

| Area | Behavior |
| --- | --- |
| **Contact form** | Stores name, email, and message. 10 submissions an hour per IP |
| **Triage** | A queued job classifies the ticket as `refund`, `shipping`, `stock`, or `other` with structured output |
| **Retrieval** | Hybrid search in Postgres: full-text plus pgvector similarity, merged with reciprocal rank fusion (RRF), reranked by Cohere |
| **Draft** | A second queued job asks for one reply plus the handbook sentences it rests on |
| **Grounding** | PHP keeps the draft only if it quotes at least one sentence and every quote appears word for word in a retrieved passage |
| **Concurrency** | A draft never overwrites a reply staff already wrote, or a ticket sent or discarded while the model ran. Send claims the ticket with one conditional update, so a double click mails once |
| **Circuit breaker** | Per provider and per model. Missing credits and connection bursts block the provider; rate limits, overloads, and 5xx bursts block one model |
| **Retries** | Waiting on a blocked provider does not count as a failure. Real failures retry with backoff and fail after three |
| **Prompt log** | Every triage and draft call is stored with its instructions, input, response, status, tokens, and error, and shown on the ticket page. Failed embeddings are logged too |
| **Inbox** | Staff see the draft, its handbook quotes, and why a draft was not kept. Send and Discard are manual |

## How a ticket moves

1. The form stores the ticket and dispatches two jobs: triage and draft.
2. Triage classifies the ticket.
3. Drafting searches the handbook and asks for one reply. The draft is kept only when it quotes at least one sentence and every quoted sentence appears in a retrieved passage. A reply with no quotes is logged as not covered, and the reply box stays empty. A kept draft is written into the reply box only when that box is still empty. A reply someone already wrote is left alone, and so is a ticket that was sent or discarded while the draft was running.
4. The ticket page shows the draft and its quotes. If the draft was not kept, the page says why. Changing the reply drops its handbook quotes.
5. Staff press Send or Discard. A second Send on the same ticket mails nothing. If the mail server refuses the message, the ticket goes back to open.

### When a provider fails

If OpenAI is down or rate limited, the circuit breaker blocks it and the triage and draft jobs wait for the block to end. Waiting does not count as a failure. The only limit is a deadline of 24 hours from submission (`ai.job_deadline_hours`); after that the job fails and `queue:retry` restarts it.

A failed call that does not block the provider counts, and the job fails after three of them (`ai.circuit_breaker.max_failures`). Bugs, bad requests, and missing credits fail the job at once. A job whose ticket was already sent or discarded stops without calling the model.

### Chunking

`handbook:resync` splits each handbook document into chunks of about 1,200 characters. Each cut lands within 120 characters of that target, at a paragraph break if there is one, then a line break, then the end of a sentence, then a space. If none of those fall in that range, the text is cut at 1,320 characters. Each chunk repeats the last 80 characters of the one before it.

Cutting at sentence ends keeps most sentences inside one chunk. Grounding needs that, because each quote must appear word for word in a single chunk.

All chunks are embedded in one call before anything is written, so a failed call leaves the stored handbook unchanged. Documents removed from the catalog are deleted.

### Retrieval

Chunks and their embeddings are stored in Postgres with pgvector. Search is full-text plus vector similarity. Full-text matches any word of the message. The two result lists are merged with RRF. A failed embedding is logged, and the ticket page says the attempt failed.

### Reranking

Cohere reranks the merged list. A chunk scoring under 0.1 is left out. If Cohere is blocked or the rerank fails, the merged order is used instead.

## Not included

- **Authentication and authorization.** Anyone can open the inbox, edit a reply, and press Send, which mails the address the ticket was submitted with. [Hookline](https://github.com/nsvetozarevic/hookline) shows authentication and authorization.
- **CAPTCHA.** The contact form is limited to 10 submissions an hour per IP.
- **Queued reply mail.** Send waits for the mail server. If the process dies after the ticket is claimed, the ticket can show `sent` without the email going out.
- **A notice while a draft is running.** Staff can still send or discard the ticket, and the draft will not overwrite them.
- **Model-driven search.** PHP searches the handbook once and sends the chunks in one prompt. The model has no search tool.
- **Query rewriting.** The whole customer message is used as the search query.
- **A shared rate limit across jobs.** Each job handles provider failures on its own.
- **A way to clear a blocked provider.** A block lasts until its timer ends.

## Where to look

| File | What it shows |
| --- | --- |
| [`app/Ai/AiCircuitBreaker.php`](app/Ai/AiCircuitBreaker.php) | Provider and model blocks, half-open state, OpenAI billing errors sent as 429 |
| [`app/Jobs/Middleware/RetryAiProvider.php`](app/Jobs/Middleware/RetryAiProvider.php) | Waiting versus counted failures, backoff |
| [`app/Jobs/Concerns/ManagesAiProvider.php`](app/Jobs/Concerns/ManagesAiProvider.php) | `$tries`, `$maxExceptions`, and the 24-hour deadline |
| [`app/Ai/Clients/TicketDraftClient.php`](app/Ai/Clients/TicketDraftClient.php) | Embed, retrieve, prompt, validate, ground, log |
| [`app/Utilities/HandbookRetriever.php`](app/Utilities/HandbookRetriever.php) | Full-text, vectors, RRF, rerank with fallback |
| [`app/Jobs/DraftTicket.php`](app/Jobs/DraftTicket.php) | Conditional write that respects staff edits |
| [`app/Actions/SendTicket.php`](app/Actions/SendTicket.php) | Claim the ticket once, mail outside any transaction, restore on failure |
| [`app/Utilities/HandbookChunker.php`](app/Utilities/HandbookChunker.php) | Character chunking with a cut window and overlap |

## Stack

| Layer | Choice |
| --- | --- |
| Runtime | PHP 8.5, Laravel 13 |
| AI | Laravel AI SDK (`laravel/ai`): OpenAI `gpt-4o-mini` and `text-embedding-3-small`, Cohere `rerank-v3.5` |
| UI | Blade, Tailwind v4 |
| Data | PostgreSQL 16 with pgvector |
| Queue / cache | Redis 7 |
| Local dev | Docker Compose (app, nginx, worker, node, postgres, redis) |
| Quality | PHPUnit, Laravel Pint; CI on push/PR |

## Run locally

**Requirements:** Docker Desktop (Compose v2), an OpenAI API key, and a Cohere API key. The tests need neither key.

```bash
docker compose up -d --build
```

On first start, the **app** container copies `.env.docker.example` to `.env.docker.local`, runs `composer install` (if `vendor/` is missing), generates `APP_KEY`, and runs `php artisan migrate`. The **node** container runs `npm ci && npm run build`. No local PHP, Composer, or Node install required.

Put the provider keys in `.env.docker.local`, then restart `app` and `worker` so they pick the new values up:

```
OPENAI_API_KEY=
COHERE_API_KEY=
```

```bash
docker compose restart app worker
```

`OPENAI_API_KEY` is triage, embeddings, and the draft. `COHERE_API_KEY` is reranking. Then load the handbook, which chunks and embeds every document:

```bash
docker compose exec app php artisan handbook:resync
```

| Service | URL / access |
| --- | --- |
| Contact form | http://localhost:8086 |
| Inbox | http://localhost:8086/inbox |
| Health | http://localhost:8086/up |
| Postgres | `localhost:5435` - user/pass/db: `inbox` / `secret` / `inbox` |
| Redis | `localhost:6381` |
| Worker | `docker compose logs -f worker` |
| Sent mail | `storage/logs/laravel.log` (`MAIL_MAILER=log`) |

Host ports are offset from the usual defaults so the stack is less likely to clash with other local services. If one is still in use, change the mapping in `compose.yaml` (e.g. `8086:80` → `9086:80`) and update `APP_URL` in `.env.docker.local` to match.

Stop: `docker compose down`. Wipe the database volume: `docker compose down -v`.

### Frontend assets

```bash
docker compose exec node npm run build   # rebuild after CSS or Blade class changes
```

## Tests and code style

With the stack running (see [Run locally](#run-locally)):

```bash
docker compose exec app composer test       # php artisan test
docker compose exec app composer cs-check   # Pint dry-run
docker compose exec app composer cs         # apply fixes
```

Tests use the `inbox_testing` database (created by the Postgres container) and fake every provider, so they do not call OpenAI or Cohere. They run on real Postgres because full-text and vector search are Postgres features.

GitHub Actions runs the same suite and Pint on every push and pull request to `main`.

## Configuration

| File | What it tunes |
| --- | --- |
| `config/ai.php` | Job deadline, circuit breaker thresholds and block times, retry limit, release jitter |
| `config/handbook.php` | Embedding model and dimensions, rerank model, retrieval limits, RRF `k`, similarity and rerank thresholds, chunk size, window, overlap, and separators |
| `database/assets/handbook-documents.php` | The handbook documents. Run `handbook:resync` after editing |
