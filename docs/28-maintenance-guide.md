# 28 — Maintenance Guide

> **Related:** [27-developer-guide](27-developer-guide.md) · [38-operational-runbook](38-operational-runbook.md) · [29-future-roadmap](29-future-roadmap.md) · [21-problems-and-solutions](21-problems-and-solutions.md)

## Common Maintenance Tasks

| Task | How | Notes |
|---|---|---|
| Add a new case | See [27-developer-guide.md](27-developer-guide.md#adding-a-new-case) | No evidence-authoring UI — evidence items need seeding/factory/tinker |
| Add a new persona | See [27-developer-guide.md](27-developer-guide.md#adding-a-persona) | Config-first; a new class only if hint-offering behavior genuinely differs |
| Add/change an LLM provider | See [27-developer-guide.md](27-developer-guide.md#adding-a-provider) | Always run the conformance harness before trusting a new provider/model |
| Rotate a provider API key | Update the corresponding `.env` variable, `config:clear` (or redeploy with cache rebuild) | No code change needed |
| Review flagged manual-grading criteria | `/admin/evaluations` | See [05-backend-architecture.md](05-backend-architecture.md) |
| Investigate a chain-exhaustion incident | Check structured logs (Phase 20) | See [38-operational-runbook.md](38-operational-runbook.md) |

## Upgrade Strategy

- **Laravel/PHP version upgrades:** the Composer advisory-block override in `composer.json` (see [21-problems-and-solutions.md](21-problems-and-solutions.md#13-composer-advisory-block-policy-rejecting-valid-laravel-releases)) is a tracked, open item — revisit whether the underlying advisories still apply before any future dependency bump, rather than assuming the override is permanently correct.
- **LLM provider/model drift:** hosted providers change and deprecate model slugs over time (explicitly flagged in the design spec as something that "drifts"). Any model change — including a provider's own silent default-model change — should be re-validated with `php artisan discussion:validate-provider` before being trusted in production, not assumed to remain conformant indefinitely.
- **Database engine:** the application targets MySQL 8/MariaDB 10.4+ specifically; the test suite's use of SQLite is a testing-isolation choice, not evidence that SQLite is a supported production target — do not assume feature parity between the two without checking (e.g., JSON column behavior, collation defaults).

## Safe Extension Points

These are the seams the architecture was explicitly designed to extend without a rewrite:

| Extension point | Mechanism |
|---|---|
| New evidence type | New `evidence_types` row + new Blade renderer — no migration, no controller change |
| New evaluation strategy | New class implementing `EvaluationStrategyInterface` — zero `EvaluationService` change |
| New LLM provider | New `LlmClientInterface` implementation (or a new config entry if OpenAI-wire-compatible) — zero `DiscussionService` change |
| New AI persona | New config entry (+ small class only if behavior genuinely differs) — zero `DiscussionService` change |
| New discussion subject (beyond `CaseAttempt`) | New `DiscussionSubjectInterface` implementation — zero `DiscussionService` change; see [29-future-roadmap.md](29-future-roadmap.md) |
| Post-evaluation side effects (badges, notifications) | New listener on the existing, currently-listener-less `CaseAttemptCompleted` event |
| Analytics on Discussion Engine cost/usage | `discussion_turns` already persists per-turn token/provider data — a new `AnalyticsService` method, no new schema |

## Known Limitations

Carried forward from [25-deployment-guide.md](25-deployment-guide.md#known-limitations-carried-into-production):

- No admin UI for evidence authoring.
- Email verification is not enforced (`verified` middleware present but currently a no-op).
- `AnalyticsService::categoryAggregates()` re-runs per-category queries (accepted trade-off at current scale — see [23-performance-optimizations.md](23-performance-optimizations.md)).
- No file storage / screenshot upload path.
- The Discussion Engine's repair-retry-on-parse-failure round trip (re-asking the model once before falling back) is not built — clients fall back to the safe default on the first parse failure (see [12-structured-output.md](12-structured-output.md)).
- Cross-session AI memory does not exist — each `DiscussionSession` is scoped to one subject, stated as a real, load-bearing limitation in the original design spec, not an oversight.
- `DiscussionSessionPolicy::view()`'s admin/instructor allowance is currently unreachable through the shipped routes (see [15-security-architecture.md](15-security-architecture.md)).

## Monitoring

No dedicated APM/monitoring service is integrated as of this documentation set. What exists today:

- **Application logs** (`storage/logs/laravel.log`) — includes structured entries on LLM fallback-chain exhaustion (attempt/case/session/persona context plus which tiers were tried and why each failed).
- **`discussion_turns.fallback_log`** — per-turn record of which tiers were skipped and why, queryable directly for a specific session's history.
- **Automated test suite** as a standing regression signal — 498 tests, run before every milestone.

See [38-operational-runbook.md](38-operational-runbook.md) for what to check first when something goes wrong in each subsystem.

## Troubleshooting Quick Reference

| Symptom | First check |
|---|---|
| 500 error on any page | `storage/logs/laravel.log` — most commonly a database connectivity issue, not an application defect |
| Engineering Discussion always "unavailable" | Is at least one `LLM_*` tier actually configured and reachable? Check Ollama liveness (`curl {base_url}/models`) first — it's always attempted |
| Ollama requests 404 | Confirm `LLM_OLLAMA_BASE_URL` includes the `/v1` suffix |
| AI reply renders empty | Check whether the configured model is a reasoning-style model exhausting `LLM_MAX_TOKENS` before visible output — see [21-problems-and-solutions.md](21-problems-and-solutions.md#3-empty-ai-reply-bubble-in-the-engineering-discussion) |
| Suspected unintended LLM API charge | Should be structurally impossible with `LLM_ALLOW_PAID_FALLBACK=false` — verify that variable's actual value first, then check `discussion_turns.provider`/`fallback_log` for which tier actually served each turn |
| A rubric criterion never gets included in a score | Confirm its `matching_type` isn't `manual` and awaiting instructor review — manual criteria are excluded from totals until reviewed, by design |
