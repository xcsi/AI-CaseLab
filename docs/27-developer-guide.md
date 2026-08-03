# 27 — Developer Guide

> **Related:** [26-configuration-reference](26-configuration-reference.md) · [17-testing-strategy](17-testing-strategy.md) · [33-folder-structure](33-folder-structure.md) · [39-glossary](39-glossary.md)
> **Audience:** a new engineer setting up AI CaseLab locally for the first time.

## Prerequisites

PHP 8.2+, Composer 2.x, Node.js 18+/npm, and a MySQL 8+/MariaDB 10.4+ instance. See [25-deployment-guide.md](25-deployment-guide.md#server-requirements) for the full PHP extension list.

## Local Setup

```bash
git clone <repository-url>
cd AICaseLab

composer install
npm install

cp .env.example .env
php artisan key:generate

# Point DB_* at your local MySQL/MariaDB instance in .env
php artisan migrate --seed        # RoleSeeder, AdminUserSeeder, EvidenceTypeSeeder, CategorySeeder
php artisan db:seed --class="Database\Seeders\DemoDataSeeder"   # optional — 3 ready-to-click demo cases

npm run dev      # Vite dev server with hot reload, OR:
npm run build    # one-off production-equivalent asset build

php artisan serve
```

`AdminUserSeeder` creates a known local admin account **only outside production environments** — check the seeder directly for current credentials rather than assuming; it deliberately no-ops when `app()->isProduction()`.

**A note on multiple local database services:** if more than one MySQL-compatible service is installed on your machine (a documented real situation on the primary development machine — see [21-problems-and-solutions.md](21-problems-and-solutions.md#12-two-mysql-compatible-servers-on-the-same-development-machine)), make sure `.env`'s `DB_HOST`/`DB_PORT` point at the one you intend to use; `.env.example` documents the generic default.

## Environment Variables

Full reference in [26-configuration-reference.md](26-configuration-reference.md). For local development, the only required block is the standard `DB_*`/`APP_*` set — every `LLM_*` variable is optional; leaving them all blank still runs the full application, with the Engineering Discussion feature reporting itself unavailable.

## Database Setup

See [07-database-design.md](07-database-design.md) for the full schema and [25-deployment-guide.md](25-deployment-guide.md#migration--seeding) for the exact seeding sequence. To reset to a clean state at any point:

```bash
php artisan migrate:fresh --seed
```

## Queue and Cache

No queue worker is required — there are no `ShouldQueue` jobs or listeners in the application; every write path runs synchronously within the request. `QUEUE_CONNECTION=database` is configured but unused headroom (see [29-future-roadmap.md](29-future-roadmap.md)). `CACHE_STORE=database` is the shipped default and needs no additional setup — the `cache` migration already exists.

## Setting Up Ollama (for local Engineering Discussion testing)

```bash
ollama pull qwen2.5:7b     # or a non-reasoning chat model — see the note below
```

Set in `.env`:

```
LLM_OLLAMA_BASE_URL=http://localhost:11434/v1
LLM_OLLAMA_MODEL=qwen2.5:7b
```

**Reasoning-model caveat:** a reasoning-style model (one that spends tokens on hidden reasoning before visible output) can exhaust `LLM_MAX_TOKENS` before writing any visible reply, producing an empty-looking response. This is a real, previously-encountered issue — see [12-structured-output.md](12-structured-output.md#case-study-the-empty-reply-bug) and [21-problems-and-solutions.md](21-problems-and-solutions.md#3-empty-ai-reply-bubble-in-the-engineering-discussion). A non-reasoning chat model avoids it entirely; the empty-reply-placeholder fallback handles it gracefully either way.

## Setting Up OpenRouter / Gemini (optional additional tiers)

```
LLM_OPENROUTER_API_KEY=<your key>
LLM_OPENROUTER_FREE_MODEL=<a :free-suffixed model id>

LLM_GEMINI_API_KEY=<your key>
LLM_GEMINI_MODEL=<a free-tier model id>
```

Both are independently optional. See [13-provider-abstraction.md](13-provider-abstraction.md#provider-conformance-results-phase-21) before treating any specific model slug as production-trustworthy — validate first.

## Testing

```bash
php artisan test                                  # full suite
php artisan test --filter=DiscussionServiceTest    # one test class
php artisan test tests/Feature/Discussion          # one directory
```

The suite runs against an in-memory SQLite database (`phpunit.xml`) — independent of your local MySQL/MariaDB service, and independent of whether any `LLM_*` variable is configured (`FakeLlmClient` is bound automatically in the testing environment). Run a new/changed test file in isolation first, then the full suite, matching this project's standing discipline — see [17-testing-strategy.md](17-testing-strategy.md).

## Debugging

- **Application logs:** `storage/logs/laravel.log`.
- **A blank/500 page:** check `storage/logs/laravel.log` first — most commonly a database connectivity issue (confirm the correct local DB service is running and `.env` points at it) rather than an application defect. Real example in [21-problems-and-solutions.md](21-problems-and-solutions.md#14-live-browser-verification-blocked-by-a-database-service-being-down).
- **`php artisan tinker`** for ad hoc inspection — wrap any write in a transaction you roll back if you're inspecting real local data you don't want mutated.
- **Discussion Engine chain-exhaustion / provider issues:** check the structured application log entries emitted on chain exhaustion (Phase 20) — they name every tier attempted and why each failed, without ever surfacing that detail to the student UI. See [38-operational-runbook.md](38-operational-runbook.md).

## Adding a New Case

There is no admin UI for evidence-item authoring (a known, documented gap — see [29-future-roadmap.md](29-future-roadmap.md)). New cases are currently authored through a combination of the admin Case/Category/Hint/Rubric-Criterion CRUD screens (for the case shell, hints, and rubric) and direct seeding/factory/tinker for evidence items. To add a fully-authored case:

1. Create the case via `/admin/cases` (or a seeder, following `DemoDataSeeder`'s pattern for a repeatable, idempotent approach).
2. Add evidence items against the case (`evidence_items.payload` shape depends on `evidence_type_id` — see [07-database-design.md](07-database-design.md)).
3. Add hints and rubric criteria via the admin UI.
4. Publish via the admin UI — `CaseCatalogService::publish()` enforces the invariant that at least one rubric criterion exists.
5. If enabling the Engineering Discussion for this case, set `discussion_enabled`, `discussion_default_persona`, and (optionally) `discussion_max_rounds` in the case editor's Engineering Discussion card.

## Adding a Persona

1. Add an entry to `config/discussion_personas.php` (`display_name`, `tone_directives`, `strictness`, `allow_hints`, `stall_threshold`, `default_max_rounds`, `acceptance_bar`).
2. If the persona's `shouldOfferHint()` **behavior** genuinely differs from both existing personas (not just its config values), create a new class implementing `AiPersonaInterface` under `app/Discussion/Personas/`. If its behavior is a straightforward variant of an existing pattern (e.g., another "never offers hints" persona), it may not need a new class at all — check `InterviewerPersona` first.
3. Register the new key in `PersonaResolver`.
4. `StartDiscussionRequest`/`RespondToDiscussionRequest` validate against `array_keys(config('discussion_personas'))` automatically — no Form Request change needed.
5. Add unit tests proving the persona's `systemPromptFragment()`, `shouldOfferHint()`, and `acceptanceBar()` behave as intended, following `MentorPersonaTest`/`InterviewerPersonaTest`'s pattern of testing at specific round counts, not just "it returns something."

See [10-persona-system.md](10-persona-system.md) and [29-future-roadmap.md](29-future-roadmap.md) for the planned future personas (Security Review, System Design Interview, Code Review, Architecture Review, DevOps Review).

## Adding a Provider

1. If the new provider speaks the OpenAI Chat Completions wire format, it needs **no new class** — add a new tier configuration and instantiate `OpenAiCompatibleLlmClient` with its `base_url`/`api_key`/`model`, the same way Ollama/OpenRouter/OpenAI already share that one class.
2. If it speaks a genuinely different wire protocol, implement `LlmClientInterface` directly (see `AnthropicLlmClient`/`GeminiLlmClient` for the pattern — request-shape construction, response-envelope text extraction, delegating everything else to `StructuredOutputParser`/`TurnClassifier`).
3. Add the tier to `LlmClientFactory` at the position appropriate to its role — **never** ahead of the existing free tiers unless there's a considered, explicit reason to reorder the fixed chain (see [13-provider-abstraction.md](13-provider-abstraction.md)). Never wire a new paid provider outside the existing `paid_fallback.allowed` guard.
4. Write `Http::fake()`-based unit tests covering the success path and the 429/timeout/5xx/genuine-error matrix, following `OpenAiCompatibleLlmClientTest`'s structure.
5. **Before treating the new provider as production-trustworthy, run the behavioral conformance harness against it:** `php artisan discussion:validate-provider {provider}`. An API-shape-correct provider is not automatically a behaviorally-safe one — see [21-problems-and-solutions.md](21-problems-and-solutions.md#2-openrouter-model-disqualified-on-the-injection-resistance-golden-transcript).

## Deployment

See [25-deployment-guide.md](25-deployment-guide.md) for the full production deployment procedure and checklists.
