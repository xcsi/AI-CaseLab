# 17 — Testing Strategy

> **Related:** [05-backend-architecture](05-backend-architecture.md) · [13-provider-abstraction](13-provider-abstraction.md) · [23-performance-optimizations](23-performance-optimizations.md) · [37-security-review](37-security-review.md)

## Testing Philosophy

Every milestone across all 22 phases ends with the full automated suite green before the next milestone starts — this is a standing process rule, not an aspiration (see [18-development-phases.md](18-development-phases.md)). Tests run in isolation first (the new test file alone) and then as part of the full suite, specifically to catch state leakage between tests before it becomes a flaky-suite problem. As of Phase 22, the suite stands at **498 passing tests** (grown from 291 at the close of Version 1 to 482 at the close of Version 2's Phase 21, plus admin-navigation and branding tests added during the subsequent visual-identity work).

## Test Types

| Layer | Tooling | What it proves |
|---|---|---|
| **Feature tests** | PHPUnit + Laravel's HTTP testing helpers, `RefreshDatabase` | Real HTTP request → real route → real middleware → real controller → real response, against an in-memory SQLite database |
| **End-to-end workflow tests** | Same tooling, longer scenarios | A complete journey (e.g., admin authoring and publishing a case through every real endpoint; a student completing a case start-to-finish; two students never leaking into each other's data) — proves each step's real response/redirect actually satisfies the next step's precondition, which many narrower per-controller tests can't individually prove |
| **Domain graph wiring tests** | Same tooling | `DomainGraphWiringTest` (Version 1) and `DiscussionGraphWiringTest` (Version 2) each build one full object graph through the real repositories/relations and walk every two-way relationship in both directions, proving the schema and models are wired correctly before any business logic is layered on top |
| **Unit tests** | PHPUnit, no database | Pure logic — persona hint-stall thresholds, `LeakageGuard` detection, `StructuredOutputParser` validation, evaluation strategies |
| **Provider conformance harness** | `php artisan discussion:validate-provider` (Console Command) | A **separate, manually-invoked tool**, deliberately outside the automated suite: it calls a real, configured LLM provider and costs whatever that provider costs. Validates *behavior* (does a model actually follow the persona and refuse to leak?), not just API shape. See [13-provider-abstraction.md](13-provider-abstraction.md) |
| **Manual smoke testing** | Real `php artisan serve` instance against real seeded data | Required for every UI-touching milestone, per this project's standing rule that "automated tests alone are not a substitute" for visually confirming a rendered page |

## Network-Free AI Testing

The entire Discussion module's test suite runs without a single real network call, via one mechanism: `DiscussionServiceProvider` binds `LlmClientInterface` to `FakeLlmClient` when `app()->environment('testing')`, and to the real `LlmClientFactory`-built chain otherwise. `FakeLlmClient` supports scripted responses (`willReturn()`), scripted exceptions (`willThrow()`, added in Phase 14 Milestone 4 specifically to script multi-tier failure sequences), call recording (`recordedCalls()`/`callCount()`), and a clear `RuntimeException` — not a confusing null or crash — when a test's response queue runs out. Provider-client-specific tests (`OpenAiCompatibleLlmClientTest`, `AnthropicLlmClientTest`, `GeminiLlmClientTest`) use `Http::fake()` instead, proving each client's own wire-format correctness (request shape, header presence/absence, role mapping) against scripted HTTP responses — still zero real network calls.

## Coverage Discipline

A dedicated Phase 12 authorization audit (Milestone 1) checked every route in `routes/web.php` against its intended Policy and found no actual Policy gap — but did find a **test-coverage** gap: several admin management test files asserted student/instructor were forbidden on write actions but never asserted a guest is redirected, relying implicitly on the route group's `auth` middleware without proving it per controller. Twelve tests were added to close that gap. The same pattern recurred and was closed again in Phase 17 Milestone 3 for the Discussion routes — cross-student-blocked was proven for `start()` but not `respond()`/`end()`/`show()`, which shared the identical middleware but had no test of their own; three tests were added. This "prove it per controller, don't infer it from the route group" discipline is now a standing check applied whenever a new protected route group is added.

## Regression Strategy

Regressions are protected primarily by **keeping the specific failure scenario as a named test**, not just fixing the bug and moving on:

- The N+1 query fix (Phase 12 Milestone 4) is protected by the audit's own measured-query-count methodology being re-runnable, not a single assertion.
- The empty-AI-reply fix ([12-structured-output.md](12-structured-output.md)) added a truly-empty-reply test *and* a whitespace-only test per provider client, specifically proving the fix's `trim()`-based emptiness check rather than a naive `=== ''` check.
- The cost-safety guarantee (paid tier never silently reached) has both a factory-level test (Phase 14) and a full end-to-end `DiscussionService`-path test (Phase 20) — the single test that protects the "never silently spend money" invariant for the life of the project.
- The rate-limiter route-model-binding bug (Phase 17 Milestone 4 — the throttle closure runs before `{attempt}` resolves to a model, so a naive `$attempt?->id` read crashed on a raw string) was caught by the first test run failing loudly, fixed before committing, and the fix itself is exercised by the same rate-limit test that originally caught it.

## Manual Testing

Every UI-facing milestone includes a manual pass against a real, running application instance and real (or realistically seeded) data — browser automation was unavailable in the primary development environment for most of the project, so manual verification was performed via scripted authenticated HTTP requests (Phase 12 close-out: 25/25 checks passed across the full student and admin journeys) or via direct `curl`/HTML-response inspection (Phase 18 Milestone 1). The subsequent visual-identity implementation work introduced real browser automation (Chrome DevTools Protocol via an MCP tool) for pixel-level before/after comparison of CSS token changes — see [19-milestones.md](19-milestones.md).

## Coverage Notes

Evidence authoring has no admin UI (that scope was never built as a separate phase), so every test that needs an evidence item attaches one via a model factory rather than through an HTTP form — a consistent, acknowledged testing shortcut, not an oversight, applied identically across `EndToEndWorkflowTest` and the Discussion end-to-end tests.
