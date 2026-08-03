# 03 — Non-Functional Requirements

> **Related:** [02-functional-requirements](02-functional-requirements.md) · [15-security-architecture](15-security-architecture.md) · [23-performance-optimizations](23-performance-optimizations.md) · [24-cost-optimizations](24-cost-optimizations.md)
> **Primary source:** `docs/01-business-requirements.md` §6 (Version 1 NFRs), extended with Version 2 (AI subsystem) NFRs.

## Version 1 Non-Functional Requirements

| ID | Requirement | How it is satisfied |
|---|---|---|
| NFR1 | **Scalability** — new case/evidence types must not require schema rewrites. | Evidence uses a type-code + JSON-payload model (`evidence_items.evidence_type_id` + `payload`), not one column set per type. Evaluation uses the Strategy pattern (`EvaluationStrategyInterface`), so a new scoring rule is a new class, not a rewrite. |
| NFR2 | **Maintainability** — Clean Code, SOLID, MVC + Service + Repository, thin controllers. | See [04-system-architecture.md](04-system-architecture.md) and [05-backend-architecture.md](05-backend-architecture.md) for the exact layering rules and the stated exception policy for when a Repository is/is not used. |
| NFR3 | **Performance** — case list/dashboard pages should respond quickly under normal load; no unbounded N+1 query growth. | A dedicated N+1 audit (Phase 12, Milestone 4) measured real query counts under scale-up and fixed the two genuine N+1s found. See [23-performance-optimizations.md](23-performance-optimizations.md). |
| NFR4 | **Security** — CSRF protection, mass-assignment guarding, Policy-based authorization, Form Request validation. | Standard Laravel protections plus a dedicated Phase 12 authorization audit against every route. See [15-security-architecture.md](15-security-architecture.md) and [37-security-review.md](37-security-review.md). |
| NFR5 | **Usability** — evidence viewers should resemble real developer tools. | Dark, monospace log/code/JSON viewers (the "Night" panel token family — see [16-design-system.md](16-design-system.md)), not a bright quiz UI. |
| NFR6 | **Extensibility** — new evaluation strategies must be pluggable without touching controllers. | `EvaluationStrategyResolver` + `EvaluationStrategyInterface`; `EvaluationService` iterates rubric criteria and resolves a strategy per criterion — controllers never branch on strategy type. |
| NFR7 | **Auditability** — student actions relevant to grading are logged. | `evidence_views`, `hint_unlocks`, `activity_log` tables; every criterion's `score_awarded` remains the auditable strategy output even after a later instructor override (`instructor_score` is a separate, nullable column — see [07-database-design.md](07-database-design.md)). |
| NFR8 | **Portability** — standard LAMP/LEMP stack. | PHP 8.2+, MySQL 8 (MariaDB-compatible), Laravel 11, Bootstrap 5/Vite frontend. No proprietary services required for Version 1; Version 2's AI subsystem is opt-in and degrades gracefully to "unavailable" without any provider configured. |
| NFR9 | **Accessibility** — responsive, reasonably keyboard-navigable UI. | Formalized in the Design System's dedicated Accessibility and Responsive sections — see [16-design-system.md](16-design-system.md) §Accessibility/§Responsive behavior. |

## Version 2 Non-Functional Requirements (AI Discussion Engine)

| ID | Requirement | How it is satisfied |
|---|---|---|
| NFR10 | **Cost safety** — the system must never silently incur LLM API charges. | Structural, not a runtime check: `LlmClientFactory::build()` only constructs a paid-tier client when `llm.paid_fallback.allowed === true` is read from configuration, and `buildPaidClient()` has exactly one call site, lexically inside that guard. Proven by an end-to-end regression test (Phase 20) exercising the full `DiscussionService` path, not just the factory in isolation. See [13-provider-abstraction.md](13-provider-abstraction.md) and [24-cost-optimizations.md](24-cost-optimizations.md). |
| NFR11 | **Provider agnosticism** — `DiscussionService` and everything above it must depend only on `LlmClientInterface`, never a concrete provider. | Verified structurally (the class has no provider-specific `instanceof`/branching) and behaviorally (`ChainedLlmClient`'s own tests use a fake client deliberately excluded from the chain and confirm it receives zero calls). |
| NFR12 | **Graceful degradation** — a fully-exhausted LLM fallback chain must never block the core (non-AI) product flow. | `NoLlmProviderAvailableException` is caught at the HTTP boundary and surfaced as a generic, non-technical "AI Discussion Unavailable" state; the Submit Diagnosis path is completely unaffected, proven by an end-to-end test asserting the underlying `CaseAttempt` is untouched when the chain is exhausted. |
| NFR13 | **Safety independent of prompting** — sensitive content (the model solution) must not leak even if the LLM disregards its system prompt (e.g., prompt injection). | `LeakageGuard` is a deterministic, non-LLM check that runs on every AI reply regardless of what the system prompt instructed — "a successfully-injected model doesn't get the last word." See [15-security-architecture.md](15-security-architecture.md). |
| NFR14 | **Behavioral conformance, not just API conformance** — a new provider/model must be validated for *behavior* (does it actually follow the persona and refuse to leak?), not just that its API responds correctly. | `docs/13-ai-discussion-engine-design.md` §15 defines a golden-transcript regression suite (`php artisan discussion:validate-provider`), run for real against Ollama, OpenRouter, and Gemini in Phase 21. See [17-testing-strategy.md](17-testing-strategy.md) and `docs/15-provider-conformance-results.md`. |
| NFR15 | **Abuse resistance** — a student must not be able to run up LLM cost/load through excessive requests. | Per-user, per-attempt rate limiting on the one endpoint that triggers an LLM call (`POST /discussion/messages`, 10 requests/minute), keyed by user ID *and* attempt ID so one attempt's usage never affects a student's budget on a different attempt. |
| NFR16 | **Observability** — a fully-exhausted fallback chain, and which tiers were skipped and why on a successful-but-degraded turn, must be recorded for operational visibility. | `discussion_turns.fallback_log` persisted per turn; structured application logging on chain exhaustion (Phase 20). See [38-operational-runbook.md](38-operational-runbook.md). |

## Explicit Constraints

- **Deployment environment:** standard shared-hosting-compatible LAMP/LEMP stack — no containerization or cloud-native infrastructure required to run the application itself (Ollama, if used, is a separate local/self-hosted process — see [25-deployment-guide.md](25-deployment-guide.md)).
- **Single institution/tenant** for the scope covered by this documentation set.
- **Content is human-authored**, not generated dynamically at runtime — cases, evidence, and rubrics come from the Admin CMS, not an LLM (the LLM's only runtime role is the Engineering Discussion reviewer).
- **Test database isolation:** the automated suite runs against an in-memory SQLite database (`phpunit.xml`), independent of the developer's local MySQL/MariaDB instance, so tests never depend on local infrastructure being up.

## Assumptions

- Content (cases, evidence, hints, rubrics) is authored by admins/instructors, not generated dynamically.
- A single MySQL-compatible database serves both the application and its read-heavy analytics queries — no separate reporting datastore.
- At least one LLM provider tier (Ollama running locally is the assumed minimum) is available for the Engineering Discussion feature to function; if none is configured, the feature degrades to the documented "unavailable" state rather than being assumed always-on.
