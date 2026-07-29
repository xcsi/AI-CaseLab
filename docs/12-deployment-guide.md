# AI CaseLab — Deployment Guide

Covers roadmap Phase 12's final deliverable: production deployment, environment verification, migration/seeding verification, smoke testing, and demo preparation. This is operational documentation, not a design doc — it describes how to *ship* what `docs/01`–`11` describe.

---

## 1. Server Requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.2+ | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo` (standard Laravel 11 set) |
| MySQL | 8.0+ | Or a wire-compatible MariaDB (10.4+ in practice — this is what local dev uses) |
| Composer | 2.x | Production install uses `--no-dev` |
| Node.js / npm | 18+ | Build-time only — assets are compiled ahead of deploy, Node is not needed at runtime |
| Web server | nginx or Apache | Document root must be `public/`, not the repo root |

**No queue worker and no cron scheduler are required.** There are no `ShouldQueue` jobs/listeners and no custom scheduled commands in this application (`routes/console.php` only has the Artisan default `inspire`) — every write path (including `EvaluationService::evaluate()`, the heaviest single operation) runs synchronously within the request. `QUEUE_CONNECTION=database` in `.env.example` is unused headroom, not a requirement.

**No file storage is in active use.** Nothing in `app/` calls `Storage::` — the "Screenshot" evidence type's upload path from the original roadmap (Phase 7) was never built (evidence authoring overall has no admin UI; see [Known Limitations](#5-known-limitations-carried-into-production) below). `php artisan storage:link` is therefore not required for this build to function, though it's harmless to run.

---

## 2. Environment Configuration

Copy `.env.example` to `.env` and set, at minimum:

```
APP_NAME="AI CaseLab"
APP_ENV=production
APP_KEY=            # generate below, never reuse the dev key
APP_DEBUG=false      # never true in production — leaks stack traces/env values
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database   # needs the sessions table (present, see §3)
SESSION_ENCRYPT=true      # recommended for production, off in dev
CACHE_STORE=database      # needs the cache table (present, see §3)

LOG_LEVEL=error            # dev default is `debug`; too verbose for production
```

```bash
php artisan key:generate --force
```

**Verified against this codebase specifically:**
- `AdminUserSeeder` already checks `app()->isProduction()` and no-ops — the known local admin account (`admin@aicaselab.test` / `password`) is **never created** when `APP_ENV=production`. Confirmed by reading the seeder, not assumed.
- No `.env` values are hardcoded or bypassed anywhere in `app/` — every config read goes through Laravel's `config()`/`env()` helpers as normal.
- `BCRYPT_ROUNDS=12` (the `.env.example` default) is an appropriate production cost factor — left as-is.

---

## 3. Build & Deploy Steps

```bash
# 1. Fetch code, then:
composer install --no-dev --optimize-autoloader
npm ci
npm run build              # compiles resources/ -> public/build/, Node not needed after this

# 2. Environment (see §2)
cp .env.example .env       # first deploy only; later deploys keep the existing .env
php artisan key:generate --force   # first deploy only

# 3. Database — migrate, then seed reference data only (see §4 for what "seed" means here)
php artisan migrate --force
php artisan db:seed --force   # RoleSeeder, AdminUserSeeder (no-ops in production), EvidenceTypeSeeder, CategorySeeder

# 4. Framework caches (safe to run on every deploy; config:clear before if APP_KEY or .env changed)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Ensure storage/ and bootstrap/cache/ are writable by the web server user
chmod -R 775 storage bootstrap/cache
```

Point the web server's document root at `public/`. No further web-server-specific config (rewrite rules, PHP-FPM pool, etc.) is described here — that's host-specific and outside this application's own concerns.

---

## 4. Migration & Seeding — Verified

Ran for real against this project's dev database (not just read the code) as part of this milestone:

```
$ php artisan migrate:fresh --seed
```

**Result: all 23 migrations applied cleanly, zero errors.** All four seeders in `DatabaseSeeder::run()` (`RoleSeeder`, `AdminUserSeeder`, `EvidenceTypeSeeder`, `CategorySeeder`) completed successfully in sequence from a completely empty database — this is the exact sequence a first production deploy runs. (Re-verified 2026-07-29 against a running MariaDB instance after the Phase 12 Milestone 4 performance work landed two more migrations than the count recorded when this section was first drafted — count re-confirmed with `php artisan migrate:status`, not assumed.)

**Demo data is a separate, opt-in seeder — not run by `db:seed` by default:**

```bash
php artisan db:seed --class="Database\Seeders\DemoDataSeeder"
```

`DemoDataSeeder` (new this milestone) creates three fully-populated, published cases — each with realistic evidence (logs, code snippets, DB snapshots, API responses), hints, and a mixed rubric (keyword + evidence-citation + manual criteria) — so a fresh environment has something real to click through immediately, without needing the admin evidence-authoring UI that doesn't exist. It's idempotent (`updateOrCreate` throughout) and deliberately kept separate from the default seeder chain, the same way `AdminUserSeeder` keeps its known-password account out of production by an explicit environment check — demo content shouldn't appear unannounced in every environment, only where someone explicitly asks for it (e.g. before a defense/demo).

---

## 5. Known Limitations Carried Into Production

Documented here rather than fixed, per this milestone's scope ("do not modify business logic unless a production blocker is discovered" — none of these are blockers, all were already deliberate, documented decisions from earlier phases):

- **No admin UI for evidence authoring.** `evidence_items` can only be populated by seeders/factories/tinker, not through `/admin`. Every evidence-dependent test and the demo seeder both work around this the same way. A real content team would need this before authoring cases beyond the three seeded demos.
- **Email verification is not enforced.** `User` does not implement `MustVerifyEmail`; the `verified` middleware on `/dashboard` is present but currently a no-op.
- **`AnalyticsService::categoryAggregates()` re-runs its per-scope queries once per category** (a deliberate Milestone-3 tradeoff: code reuse over query count — see the CHANGELOG's Milestone 4 performance entry). Fine at the category counts this project runs at; would need batching if categories grew into the hundreds.
- **No file storage / screenshot upload** — see §1. Not a regression, this was never built.

None of these block a working deployment — the full student and admin journeys (browse → investigate → submit → get scored; author → publish → review) work end to end on real data, as re-confirmed by this milestone's smoke test (see §6 below and the CHANGELOG entry for this milestone for the executed checklist and results).

---

## 6. Smoke Test — Executed 2026-07-29

Browser automation wasn't available in this environment, so the smoke test was run as scripted authenticated HTTP requests against a live `php artisan serve` instance backed by the real MariaDB dev database (fresh `migrate:fresh --seed` + `DemoDataSeeder`) — real sessions, real CSRF tokens, real redirects, not a mock. 25/25 checks passed:

**Student journey** (fresh registration → completed, scored attempt):
register → dashboard → case catalog (confirms seeded case titles render) → case detail page (`incidents/{slug}`) → start attempt → investigation workspace → evidence view recorded (`fetch` + `X-CSRF-TOKEN`) → notebook autosave (`PATCH`, JSON body) → hint unlock → diagnosis report form → diagnosis submit (redirects to Performance Review) → Performance Review shows a score → Work History reachable.

**Admin journey** (real login, not a stub):
login → Admin Dashboard → Cases index → Categories index → Analytics → Evaluations (manual-review queue) — confirmed the just-submitted diagnosis appears in the queue (its case has a `manual` rubric criterion) → opened the review edit form → submitted scores/comments for all three criteria → redirected back to the queue, confirming the manual-review write path works end to end, not just the read side.

**Guest / negative checks:** unauthenticated `/dashboard` and `/admin` both redirect (302) rather than leak content.

**Bugs found while writing the test — and how they were resolved (not code changes):** the first draft assumed `/incidents/{id}`, plain `_token` form fields for the workspace's `fetch()` calls, and `root_cause`/`proposed_fix` field names. All three were script mistakes, not app bugs — corrected by reading `routes/web.php` (case routes bind on `{case:slug}`), `resources/views/investigation/show.blade.php` (JS endpoints use the `<meta name="csrf-token">` value via an `X-CSRF-TOKEN` header, not a form field), and `StoreDiagnosisRequest` (`root_cause_text`/`proposed_fix_text`). No application code was touched to make the smoke test pass.

Script: `smoke_test.sh` (session scratchpad, not committed — a throwaway verification script, not a maintained test asset; the committed regression coverage lives in `tests/Feature`).

---

## 7. Demo Preparation

For a live walkthrough (graduation defense, portfolio demo), on top of the standard deploy steps in §3:

```bash
php artisan db:seed --class="Database\Seeders\DemoDataSeeder"
```

This produces three ready-to-click, fully-scored-capable published cases (confirmed idempotent — re-running it does not duplicate cases, evidence, hints, or rubric criteria):

| Case | Category | Difficulty | Demonstrates |
|---|---|---|---|
| API Returning 500 on Checkout | Backend | Medium | log + code + API-response evidence, mixed keyword/citation/manual rubric, manual-review queue |
| Login Failures After Password Reset | Backend | Easy | DB-snapshot + log evidence, fast end-to-end path for a short demo |
| Dashboard Queries Timing Out | Database | Hard | log + code + DB-snapshot evidence, N+1/indexing root cause — pairs naturally with a mention of this project's own Phase 12 performance work |

Suggested demo script: log in as `admin@aicaselab.test` to show the authoring/rubric/manual-review side first, then register or log in as a second (student) account and run one case start-to-finish to show the scored Performance Review. Both paths were exercised in §6's smoke test immediately before this was written.

---

## 8. Production Deployment Checklist

- [ ] `composer install --no-dev --optimize-autoloader` completes clean
- [ ] `npm ci && npm run build` completes clean, `public/build/manifest.json` present
- [ ] `.env` created from `.env.example` with production values (§2) — `APP_ENV=production`, `APP_DEBUG=false`, real `APP_URL`, real DB credentials
- [ ] `php artisan key:generate --force` run once, `APP_KEY` never reused from dev
- [ ] `php artisan migrate --force` run, zero errors
- [ ] `php artisan db:seed --force` run (reference data only — `RoleSeeder`, `AdminUserSeeder`, `EvidenceTypeSeeder`, `CategorySeeder`)
- [ ] Confirmed `AdminUserSeeder` did **not** create the known dev admin account (it no-ops under `APP_ENV=production` — verify by checking `users` table, not by trusting this line)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache` run
- [ ] `storage/` and `bootstrap/cache/` writable by the web server user
- [ ] Web server document root points at `public/`, not the repo root
- [ ] HTTPS terminated in front of the app (`SESSION_ENCRYPT=true` in `.env` assumes this)
- [ ] Smoke test (§6) re-run against the deployed URL, not just localhost

## 9. Release Checklist

- [ ] Full automated test suite green (`php artisan test`) — see CHANGELOG for the count recorded at this milestone's close
- [ ] Manual smoke test executed and passing (§6)
- [ ] `CHANGELOG.md` `[Unreleased]` section has an entry for this milestone
- [ ] `docs/12-deployment-guide.md` (this file) reflects the current migration count and any new known limitations
- [ ] No uncommitted changes to business logic beyond what this milestone's scope allows (deployment/testing/docs only, per the standing delivery-process rule — any exception must be an actual production blocker, called out explicitly)
- [ ] Working tree clean, feature branch pushed to `origin`
- [ ] Demo data available for a live walkthrough if one is scheduled (§7)
- [ ] Known limitations (§5) still accurate — re-read, don't assume unchanged
