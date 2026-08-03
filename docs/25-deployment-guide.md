# 25 — Deployment Guide

> **Related:** [26-configuration-reference](26-configuration-reference.md) · [38-operational-runbook](38-operational-runbook.md) · [13-provider-abstraction](13-provider-abstraction.md)
> **Primary source:** `docs/12-deployment-guide.md` (the full, verified operational guide — reproduced and reorganized here; that document remains the canonical detailed reference and should be updated in lockstep with this one).

## Server Requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.2+ | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo` |
| MySQL | 8.0+ | Or a wire-compatible MariaDB (10.4+ — what local development actually uses) |
| Composer | 2.x | Production install uses `--no-dev` |
| Node.js / npm | 18+ | Build-time only — assets are compiled ahead of deploy; Node is not required at runtime |
| Web server | nginx or Apache | Document root must be `public/`, not the repository root |

**No queue worker and no cron scheduler are required.** There are no `ShouldQueue` jobs/listeners and no custom scheduled commands — every write path, including the heaviest single operation (`EvaluationService::evaluate()`), runs synchronously within the request. `QUEUE_CONNECTION=database` is unused headroom, not a requirement (see [29-future-roadmap.md](29-future-roadmap.md) for when this would become load-bearing).

**No file storage is in active use.** No code calls `Storage::` — the Screenshot evidence type's upload path was never built (evidence authoring overall has no admin UI). `php artisan storage:link` is not required to function, though harmless to run.

## Environment Configuration

Copy `.env.example` to `.env` and set at minimum: `APP_ENV=production`, `APP_KEY` (generated, never reused from dev), `APP_DEBUG=false`, real `APP_URL`, real DB credentials, `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `CACHE_STORE=database`, `LOG_LEVEL=error`. Full variable reference in [26-configuration-reference.md](26-configuration-reference.md).

```bash
php artisan key:generate --force
```

**Verified specifically for this codebase:** `AdminUserSeeder` checks `app()->isProduction()` and no-ops — the known local admin account is never created when `APP_ENV=production`; no `.env` value is hardcoded or bypassed anywhere in `app/`.

## Build & Deploy Steps

```bash
# 1. Fetch code
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 2. Environment (first deploy only)
cp .env.example .env
php artisan key:generate --force

# 3. Database
php artisan migrate --force
php artisan db:seed --force   # RoleSeeder, AdminUserSeeder (no-op in production), EvidenceTypeSeeder, CategorySeeder

# 4. Framework caches (safe on every deploy)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Permissions
chmod -R 775 storage bootstrap/cache
```

## Migration & Seeding

23 migrations, verified with a fresh `migrate:fresh --seed`: zero errors. Demo data is a **separate, opt-in** seeder, not run by default:

```bash
php artisan db:seed --class="Database\Seeders\DemoDataSeeder"
```

Creates three fully-populated, published demo cases (see [Demo Preparation](#demo-preparation) below), confirmed idempotent (`updateOrCreate` throughout — re-running does not duplicate content).

## Known Limitations Carried Into Production

- **No admin UI for evidence authoring** — `evidence_items` can only be populated by seeders/factories/tinker.
- **Email verification is not enforced** — `User` does not implement `MustVerifyEmail`; the `verified` middleware on `/dashboard` is present but currently a no-op.
- **`AnalyticsService::categoryAggregates()` re-runs per-category queries** — a deliberate trade-off (see [23-performance-optimizations.md](23-performance-optimizations.md)), fine at current category counts.
- **No file storage / screenshot upload** — never built, not a regression.

None of these block a working deployment — the full student and admin journeys work end to end on real data.

## Smoke Test

Executed as scripted authenticated HTTP requests against a live `php artisan serve` instance backed by real MariaDB data (browser automation was unavailable in the primary development environment) — 25/25 checks passed, covering the complete student journey (register → catalog → case detail → start → workspace → evidence view → notebook autosave → hint unlock → diagnosis submit → scored Performance Review → Work History) and admin journey (login → Dashboard → Cases → Categories → Analytics → Evaluations manual-review queue, including opening and saving a real review), plus guest-redirect negative checks. See [21-problems-and-solutions.md](21-problems-and-solutions.md) for how three script mistakes (assumed URL/field names) were found and corrected without touching application code.

## Demo Preparation

```bash
php artisan db:seed --class="Database\Seeders\DemoDataSeeder"
```

| Case | Category | Difficulty | Demonstrates |
|---|---|---|---|
| API Returning 500 on Checkout | Backend | Medium | Log + code + API-response evidence, mixed keyword/citation/manual rubric, manual-review queue |
| Login Failures After Password Reset | Backend | Easy | DB-snapshot + log evidence, fast end-to-end path |
| Dashboard Queries Timing Out | Database | Hard | Log + code + DB-snapshot evidence, N+1/indexing root cause |

Suggested demo script: log in as the admin account to show authoring/rubric/manual-review first, then run one case start-to-finish as a student account to show the scored Performance Review.

## Production Deployment Checklist

- [ ] `composer install --no-dev --optimize-autoloader` completes clean
- [ ] `npm ci && npm run build` completes clean, `public/build/manifest.json` present
- [ ] `.env` created with production values — `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`, real DB credentials
- [ ] `php artisan key:generate --force` run once, `APP_KEY` never reused from dev
- [ ] `php artisan migrate --force` run, zero errors
- [ ] `php artisan db:seed --force` run (reference data only)
- [ ] Confirmed `AdminUserSeeder` did **not** create the known dev admin account (check the `users` table directly)
- [ ] `config:cache`/`route:cache`/`view:cache` run
- [ ] `storage/` and `bootstrap/cache/` writable by the web server user
- [ ] Document root points at `public/`
- [ ] HTTPS terminated in front of the app (`SESSION_ENCRYPT=true` assumes this)
- [ ] Smoke test re-run against the deployed URL, not just localhost

## Release Checklist

- [ ] Full automated test suite green
- [ ] Manual smoke test executed and passing
- [ ] `CHANGELOG.md` updated
- [ ] This guide reflects the current migration count and known limitations
- [ ] No uncommitted changes beyond the milestone's stated scope
- [ ] Working tree clean, feature branch pushed
- [ ] Demo data available if a live walkthrough is scheduled
- [ ] Known limitations re-read, not assumed unchanged

## LLM Provider Setup (Engineering Discussion)

Purely additive to everything above — a deployment with **no `LLM_*` variables set at all** still works; Ollama's tier is always attempted, and an unreachable local instance simply fails its liveness check and falls through. If every tier is unreachable/unconfigured, the Discussion feature reports itself unavailable in the UI and never blocks the core diagnosis path in front of it. Full `.env` shape, Ollama installation steps (including the `/v1` endpoint requirement), and provider conformance status in [26-configuration-reference.md](26-configuration-reference.md) and [13-provider-abstraction.md](13-provider-abstraction.md).
