# 26 — Configuration Reference

> **Related:** [25-deployment-guide](25-deployment-guide.md) · [13-provider-abstraction](13-provider-abstraction.md) · [10-persona-system](10-persona-system.md)

## Core Application (`.env`)

| Variable | Purpose | Production guidance |
|---|---|---|
| `APP_NAME` | Display name | `"AI CaseLab"` |
| `APP_ENV` | Environment | `production` |
| `APP_KEY` | Encryption key | Generate fresh via `php artisan key:generate --force`; never reuse the dev key |
| `APP_DEBUG` | Debug mode | `false` — leaks stack traces/env values if `true` |
| `APP_URL` | Base URL | Real production domain |
| `DB_CONNECTION` / `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database | MySQL 8+/MariaDB 10.4+ |
| `SESSION_DRIVER` | Session storage | `database` (needs the `sessions` table, present) |
| `SESSION_ENCRYPT` | Session encryption | `true` in production (assumes HTTPS termination in front) |
| `CACHE_STORE` | Cache storage | `database` (needs the `cache` table, present) |
| `LOG_LEVEL` | Log verbosity | `error` in production (dev default is `debug`) |
| `QUEUE_CONNECTION` | Queue driver | `database` — configured but currently unused (no `ShouldQueue` jobs exist); reserved headroom for future async work, see [29-future-roadmap.md](29-future-roadmap.md) |
| `BCRYPT_ROUNDS` | Password hash cost | `12` (the shipped default) — appropriate as-is |

## LLM Provider Configuration (`config/llm.php`, sourced from `.env`)

```
LLM_MAX_TOKENS=300

LLM_OLLAMA_BASE_URL=http://localhost:11434/v1
LLM_OLLAMA_MODEL=qwen2.5:7b

LLM_OPENROUTER_API_KEY=
LLM_OPENROUTER_FREE_MODEL=meta-llama/llama-3.1-8b-instruct:free

LLM_GEMINI_API_KEY=
LLM_GEMINI_MODEL=gemini-1.5-flash

LLM_ALLOW_PAID_FALLBACK=false
LLM_PAID_FALLBACK_PROVIDER=
LLM_OPENAI_API_KEY=
LLM_OPENAI_MODEL=gpt-4o-mini
LLM_ANTHROPIC_API_KEY=
LLM_ANTHROPIC_MODEL=claude-3-5-haiku-20241022
```

| Variable | Purpose | Notes |
|---|---|---|
| `LLM_MAX_TOKENS` | Hard cap on AI reply length | ~300 by default — cost control and correct persona behavior at once |
| `LLM_OLLAMA_BASE_URL` | Local Ollama endpoint | **Must include the `/v1` suffix** — a bare host:port 404s on every request (see [21-problems-and-solutions.md](21-problems-and-solutions.md)) |
| `LLM_OLLAMA_MODEL` | Local model tag | Always attempted regardless of other config; a not-running Ollama just fails its liveness check quickly |
| `LLM_OPENROUTER_API_KEY` / `LLM_OPENROUTER_FREE_MODEL` | OpenRouter free-tier config | Tier 2, independently optional |
| `LLM_GEMINI_API_KEY` / `LLM_GEMINI_MODEL` | Gemini free-tier config | Tier 3, independently optional |
| `LLM_ALLOW_PAID_FALLBACK` | **The cost-safety gate** | Must stay `false` unless a paid tier is a deliberate, budgeted choice. When `false`, no paid-tier client is ever constructed — not even a disabled one. See [13-provider-abstraction.md](13-provider-abstraction.md) |
| `LLM_PAID_FALLBACK_PROVIDER` | Which paid provider | `openai` or `anthropic` — required only if the flag above is `true` |
| `LLM_OPENAI_API_KEY` / `LLM_OPENAI_MODEL`, `LLM_ANTHROPIC_API_KEY` / `LLM_ANTHROPIC_MODEL` | Paid-tier credentials | Inert unless **both** the flag is `true` and this specific provider is selected |

**`.env.example` does not ship any `LLM_*` keys pre-filled**, by deliberate decision — add only the tiers actually in use. A deployment with no `LLM_*` variables set at all still works; the Discussion feature simply reports itself unavailable.

**Model slug caveat:** the model IDs shown above are illustrative defaults, not vetted recommendations. As of the Phase 21 conformance validation, no tested model slug is recommended as a trusted shipped default — see [13-provider-abstraction.md](13-provider-abstraction.md#provider-conformance-results-phase-21) and `docs/15-provider-conformance-results.md`. Validate any new choice with `php artisan discussion:validate-provider {provider}` before relying on it.

## Persona Configuration (`config/discussion_personas.php`)

One entry per persona (`mentor`, `interviewer`), each carrying: `display_name`, `tone_directives`, `strictness`, `allow_hints`, `stall_threshold`, `default_max_rounds`, `acceptance_bar`. See [10-persona-system.md](10-persona-system.md) for the full field-by-field meaning. Adding a persona is a new config entry plus (if its hint-offering behavior genuinely differs, not just its config values) a small new class implementing `AiPersonaInterface` — see [27-developer-guide.md](27-developer-guide.md#adding-a-persona).

## Ollama Installation Reference

1. Install Ollama for the target OS and confirm it's running: `ollama --version`.
2. Pull a model: `ollama pull qwen2.5:7b` (or a validated alternative — see the model-slug caveat above).
3. Confirm `LLM_OLLAMA_BASE_URL` includes `/v1` — this is the shipped default, but worth double-checking after any manual override.
4. Verify: `curl http://localhost:11434/v1/models` should return a JSON model list, not a connection error or 404.

## Web Server / Runtime

Document root must be `public/`. `storage/` and `bootstrap/cache/` must be writable by the web server user. No queue worker, no cron scheduler, and no file-storage service are required to run the application (see [25-deployment-guide.md](25-deployment-guide.md)).
