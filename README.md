# AI Support Inbox

[![Tests](https://github.com/nsvetozarevic/ai-support-inbox/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/nsvetozarevic/ai-support-inbox/actions/workflows/tests.yml)

Support inbox for a made-up shop, Northshore Supply, built with Laravel and the Laravel AI SDK. A customer writes in and the app opens a ticket. Queue workers classify it and draft a reply from the shop handbook. Staff edit the draft, send it, or discard the ticket.

The model proposes. PHP checks the draft. A person sends the mail.

Portfolio demo, not a production app. It shows the AI side of a support inbox: retrieval, grounding, and handling provider failures. Authentication and authorization are out of scope; [Hookline](https://github.com/nsvetozarevic/hookline) is the project that shows them. Here the inbox is public, so do not deploy it as is. See [Out of scope](#out-of-scope).

## What it does

| Area | Behavior |
| --- | --- |
| **Contact form** | Stores name, email, and message. 10 submissions an hour per IP, because each ticket costs about four paid API calls |
| **Triage** | A queued job classifies the ticket as `refund`, `shipping`, `stock`, or `other` with structured output |
| **Retrieval** | Hybrid search in Postgres: full-text plus pgvector similarity, merged with reciprocal rank fusion (RRF), reranked by Cohere |
| **Draft** | A second queued job asks for one reply plus the handbook sentences it rests on |
| **Grounding** | PHP keeps the draft only if it quotes at least one sentence and every quote appears word for word in a retrieved passage |
| **Concurrency** | A draft never overwrites a reply staff already wrote, or a ticket sent or discarded while the model ran. Send claims the ticket with one conditional update, so a double click mails once |
| **Circuit breaker** | Per provider and per model. Missing credits and connection bursts block the provider; rate limits, overloads, and 5xx bursts block one model |
| **Retries** | Waiting on a blocked provider does not count as a failure, so an outage does not lose tickets. Real failures retry with backoff and fail after three |
| **Prompt log** | Every model call is stored with its instructions, input, response, status, tokens, and error, and shown on the ticket page |
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

### Retrieval

Handbook pages are split into chunks, embedded, and stored in Postgres with pgvector. Search is full-text plus vector similarity. Full-text lets any word of the message match, because a whole customer message rarely shares every word with one passage. The two lists are merged. A failed embedding is logged, and the ticket page says the attempt failed.

### Reranking

Cohere reranks the merged list. A chunk scoring under 0.1 is left out. That number is deliberately low until it is measured on real questions. If Cohere is blocked or the rerank fails, the merged order is used instead.

OpenAI does triage, embeddings, and the draft. Cohere only reranks.

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

No Laravel Scout, Meilisearch, or hosted vector store. Retrieval stays in Postgres.

## Run locally

**Requirements:** Docker Desktop (Compose v2), an OpenAI API key, and a Cohere API key.

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

`OPENAI_API_KEY` is triage, embeddings, and the draft. `COHERE_API_KEY` is reranking. Then load the handbook, which chunks and embeds every page:

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
| `database/assets/handbook-documents.php` | The handbook pages. Run `handbook:resync` after editing |

## Out of scope

These are left out on purpose.

- **No authentication or authorization.** This demo is about the AI pipeline, so auth is out of scope; [Hookline](https://github.com/nsvetozarevic/hookline) has authentication and authorization in place. Here the inbox is public. There is no login, no staff account, and no check on who may read or send a ticket. So anyone can open a ticket with someone else's email address, then press Send and mail that person any text from the shop's mailer. A real shop would put the inbox behind a staff login.
- **No CAPTCHA.** The form allows ten submissions an hour per IP address. That stops one machine, not many machines on different addresses. A real shop would put a CAPTCHA, such as Cloudflare Turnstile, in front of the form.
- **No automatic email.** Mail goes out only when a person presses Send.
- **No queued reply mail.** Send waits for the mail server. If the process dies after the ticket is claimed and before the mail returns, the ticket says `sent` without the email. A queued mail after the claim would make that retryable.
- **No notice while a draft is running.** The ticket page does not say that a draft is in progress. Staff can still send or discard, and the job will not overwrite that. A real shop could mark the ticket when the draft job starts and show that on the page.
- **The model does not search.** PHP searches the handbook and puts the matching chunks in one draft prompt. The model answers once. It has no search tool it can call, and then call again.
- **The customer's message is not rewritten** into a different search query before retrieval.
- **No shared rate limit across jobs.** Each job retries its own provider failure. There is no production traffic to throttle.
- **No button to clear a blocked provider.** An open circuit stays blocked until its timer ends. The inbox could tell staff and let them clear it after they fix the key, for example by adding credits. That screen is not built.
