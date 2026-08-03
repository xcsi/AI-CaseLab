# 38 — Operational Runbook

> **Related:** [28-maintenance-guide](28-maintenance-guide.md) · [25-deployment-guide](25-deployment-guide.md) · [13-provider-abstraction](13-provider-abstraction.md)
> Practical, incident-response-oriented reference. See [28-maintenance-guide.md](28-maintenance-guide.md) for planned maintenance tasks and [27-developer-guide.md](27-developer-guide.md) for local development.

## General Incident Triage

1. Check `storage/logs/laravel.log` first — the majority of application-level failures are visible here immediately.
2. Confirm the database service is reachable (`php artisan migrate:status` is a quick liveness check that also confirms schema state).
3. Confirm `.env` matches the expected environment (`APP_ENV`, `APP_DEBUG` — `APP_DEBUG=true` in production is itself an incident, since it leaks stack traces and configuration values).

## Engineering Discussion Is Unavailable for All Students

| Check | How | Expected |
|---|---|---|
| Is Ollama reachable? | `curl {LLM_OLLAMA_BASE_URL}/models` | JSON model list, not a connection error |
| Is the `/v1` suffix present in `LLM_OLLAMA_BASE_URL`? | Read `.env`/`config/llm.php` | Must include `/v1` — a bare host:port 404s every request |
| Are any hosted free tiers configured? | Read `.env` for `LLM_OPENROUTER_API_KEY`/`LLM_GEMINI_API_KEY` | If Ollama is the only tier and it's down, the feature is correctly unavailable — this is expected behavior, not a bug, if no other tier is configured |
| What does the structured chain-exhaustion log say? | Search `storage/logs/laravel.log` for the `DiscussionController::logChainExhausted()` entries | Names every tier attempted and why each failed |

**Important:** the core, non-AI application (browsing, investigating, submitting, scoring) is architecturally guaranteed to be unaffected by this condition — if the whole application is down, the cause is elsewhere; see General Incident Triage above.

## Suspected Unintended LLM Cost

This should be structurally impossible per [13-provider-abstraction.md](13-provider-abstraction.md), but if a charge is observed:

1. Check `LLM_ALLOW_PAID_FALLBACK` in the deployed `.env` — if `false`, no paid-tier client was ever constructed anywhere in the request path (verify this claim by reading `LlmClientFactory::build()` directly if in doubt).
2. Query `discussion_turns` for rows where `provider` is `openai` or `anthropic` — this is the audit trail of which tier actually served every turn, recorded per turn, not just per session.
3. If a charge genuinely originated from this application with `LLM_ALLOW_PAID_FALLBACK=false`, this is a critical-severity finding — it would mean the structural guarantee described in [13-provider-abstraction.md](13-provider-abstraction.md) failed, which should not be possible per the current code and its regression test (Phase 20). Escalate and treat the underlying test/guarantee as compromised until root-caused.

## A Student Reports the AI "Said Something Wrong"

1. Locate the relevant `discussion_sessions`/`discussion_turns` rows for the attempt (visible directly in the database, and to the student/instructor on Performance Review).
2. Check `internal_note` on the AI turn(s) in question — the model's own stated reasoning is persisted, giving direct insight into why a given verdict was reached.
3. If the concern is a potential leak of the model solution, this should have been caught by `LeakageGuard` before the reply was ever persisted — if a leak is confirmed to have reached a student, this is a defense-in-depth failure and should be treated with the same severity as the cost-safety scenario above, and the specific reply text should be added as a new golden-transcript regression case.
4. If the concern is tone/behavior (not a leak), consider whether the persona's system prompt or the specific provider/model needs conformance re-validation — see [13-provider-abstraction.md](13-provider-abstraction.md#provider-conformance-results-phase-21).

## A Case Won't Publish

Check `CaseCatalogService::publish()`'s invariant — at least one rubric criterion must exist. The admin UI should already surface this via a disabled-with-tooltip Publish button; if it doesn't, that's a UI regression against the Phase 12 validation-audit behavior.

## Evaluation Score Looks Wrong

1. Confirm which criteria are `manual` matching type — these are recorded but excluded from the total/max score until an instructor reviews them, by design (not a bug).
2. Confirm whether `instructor_score` is set on any criterion — if so, `EvaluationCriterionResult::effectiveScore()` determines which value (auto-computed `score_awarded` or the instructor override) is actually used in the total.
3. Confirm `max_possible_score` on the attempt reflects any hint penalties incurred — the evaluation's ceiling is `min(sum of gradable criteria weights, attempt.max_possible_score)`.

## Rotating Credentials

LLM provider API keys: update the relevant `.env` variable, then `php artisan config:clear && php artisan config:cache` (or redeploy with cache rebuild). No code change or restart of any queue worker is needed — there is no queue worker.

## Rollback

Standard Laravel rollback procedure applies: `php artisan migrate:rollback` for the most recent migration batch (test against a copy of production data first — no migration in this project has been specifically designed with a verified-safe-on-real-data rollback path beyond Laravel's own generated `down()` methods). Application code rollback is a standard git-based deployment revert; no stateful external service (queue, cache warm-up, file storage) needs coordinated rollback alongside it.

## Contacts and Escalation

This is a graduation project without a formal on-call rotation; for the purposes of this documentation package, escalation means: consult [21-problems-and-solutions.md](21-problems-and-solutions.md) for whether the specific symptom matches a previously-encountered issue, then [30-lessons-learned.md](30-lessons-learned.md) for the general debugging principles applied throughout the project's history, before treating any new failure as unprecedented.
