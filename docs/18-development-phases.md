# 18 — Development Phases

> **Related:** [19-milestones](19-milestones.md) · [20-design-decisions](20-design-decisions.md) · [31-architecture-decision-records](31-architecture-decision-records.md) · `CHANGELOG.md`
> **Primary source:** `CHANGELOG.md` (Phases 1–12, in full detail) and the complete git commit history (Phases 13–22, in full detail). This document covers all 22 phases; the milestone-by-milestone breakdown within each phase is in [19-milestones.md](19-milestones.md).

## How to Read This Document

Version 1 (Phases 1–12) built the complete student/admin case-investigation platform. Version 2 (Phases 13–22) added the AI Discussion Engine as a strictly additive module on top of an unchanged Version 1. Every phase below states its objective, what was actually delivered, the key architectural reasoning behind it, and its final outcome (test count at close).

---

## Phase 1 — Laravel Project Setup

**Objective:** stand up the technical foundation.
**Delivered:** Laravel 11 merged into the repository root; Laravel Breeze (Blade stack) for auth scaffolding; Bootstrap 5 wired through Vite, replacing Breeze's default Tailwind/Alpine frontend; student shell (`layouts/app.blade.php` + navbar) and admin shell (`layouts/admin.blade.php` + sidebar) built per the approved UI/UX design, with placeholder routes for pages built in later phases; development database switched from SQLite to MySQL.
**Design decision:** Bootstrap over Tailwind/Alpine (Breeze's default) — see [20-design-decisions.md](20-design-decisions.md).
**Known issue carried forward:** Composer's advisory-block policy rejected every Laravel 11.31–11.55 release at the time (three medium/high-severity advisories with fixes only in later major versions); overridden via `config.policy.advisories.block` in `composer.json` to allow installation — tracked as an open decision, not silently suppressed.
**Outcome:** foundation in place; no automated tests yet (pre-domain-model).

## Phase 2 — Authentication & Roles

**Objective:** role-based access control.
**Delivered:** `roles` table (`student`/`instructor`/`admin`) and `users.role_id`; `UserRole` backed enum as the single source of truth for role names; `EnsureUserHasRole` middleware (aliased `role`), gating `/admin/*`; `RoleSeeder` + `AdminUserSeeder` (local admin account, skipped outside non-production environments); registration refactored onto `RegisterUserRequest` + `UserRegistrationService` (always assigns the `student` role) instead of inline controller validation.
**Tests added:** guest/student/instructor/admin access to `/admin`; registration role default.

## Phase 3 — Database Schema & Models

**Objective:** the complete Version 1 domain schema.
**Delivered:** all 15 domain tables (see [07-database-design.md](07-database-design.md)); five PHP backed enums; Eloquent models with full relationships including the `diagnoses` ↔ `evidence_items` citation pivot; `EvidenceTypeSeeder` + `CategorySeeder`; Repository interfaces + Eloquent implementations for the six aggregate roots, bound via `RepositoryServiceProvider`.
**Architectural reasoning:** a pre-migration architecture review (documented in [20-design-decisions.md](20-design-decisions.md)) changed the design *before* any migration was written — enum-like columns became `string` + PHP backed enum instead of native SQL `ENUM`; `diagnoses.cited_evidence_ids` (originally a JSON array) was normalized into the `diagnosis_evidence_citations` pivot table; `activity_log` was added.
**Tests added:** `DomainGraphWiringTest` — builds one full case graph through the repositories and asserts every relationship resolves both directions.

## Phase 4 — Admin CMS

**Objective:** the content-authoring platform.
**Delivered:** Case/Category/Hint/Rubric-Criterion CRUD; publish workflow (`CaseCatalogService::publish()`, enforcing the invariant that a case needs at least one rubric criterion before it can publish — the evidence-count invariant was intentionally stubbed with a documented `TODO` since evidence authoring didn't exist yet); Admin Dashboard (stat cards, Needs Attention, Recent Activity via a new `ActivityLogService`).
**Outcome:** 110 tests passing at close.

## Phase 5 — Student Engineering Office

**Objective:** the complete student investigation journey, across 7 milestones (Shell, Inbox, Assigned Incidents, Investigation Workspace + Evidence Explorer/Viewer, Hint Unlocking, Timer/Progress, Diagnosis Submission, Performance Review). Full milestone breakdown in [19-milestones.md](19-milestones.md).
**Process note:** before writing any code, a full UX design spec for the entire student journey (Login → Shell → Inbox → Assigned Incidents → Incident Briefing → Investigation Workspace → Submit Diagnosis → Performance Review → Work History) was produced and approved as binding — implementation then proceeded strictly one screen at a time.
**Post-completion architectural review (no behavior change):** a full pass over every controller/service/view added across all 7 milestones against SOLID, reusability, route organization, repository usage, security, and responsive-behavior criteria. Extracted `App\Support\Badge` and `App\Support\ScoreFormatter` (each previously duplicated across 3–4 view files); routed several services' writes through existing Repository bindings instead of direct Eloquent calls; grouped six `/investigation/{attempt}/*` routes under one middleware/prefix block; hardened evidence-tab JS against a stored-XSS vector (see [15-security-architecture.md](15-security-architecture.md)). Verified via the full suite (222/222 passing, unchanged before and after) and an identical route table — proof that a refactor changed structure without changing behavior.
**Outcome:** 222 tests passing at close of the review.

## Phase 6 — Incident Investigation Workspace (Evaluation Engine, Manual Review, Analytics)

**Objective:** automated rubric-based scoring, instructor override, and cohort analytics — 4 milestones.
**Delivered:** the Strategy-pattern Evaluation Engine (`EvaluationStrategyInterface` + `KeywordMatchStrategy`/`EvidenceCitationStrategy`/`ManualReviewStrategy` + `EvaluationStrategyResolver`); `EvaluationService::evaluate()`; manual-review workflow with `instructor_score` as a separate, nullable override column (never overwriting the auditable `score_awarded`) and `EvaluationCriterionResult::effectiveScore()` deciding which wins; `AnalyticsService`'s five aggregate methods, each taking an optional `?array $caseIds` scope so platform-wide, single-case, and category-level rollups share one implementation; the read-only Admin Analytics Dashboard.
**Design decision:** manual-review criteria are recorded but excluded from the total/max score until reviewed — including their weight would permanently under-score any case using one, since no instructor workflow existed yet to score them.
**Bonus side effect, explicitly noted:** `CaseAttemptService::start()`'s reattempt gate — previously a documented no-op because no attempt had ever reached `Completed` status — became live the moment attempts started actually reaching `Completed`.

## Phase 7 — Evidence Management

Per the roadmap sequencing, evidence-item CRUD/authoring was never built as its own admin UI phase — evidence exists via seeded/factory data throughout the project's life. This is a real, acknowledged scope gap, not an oversight; see [29-future-roadmap.md](29-future-roadmap.md) and [17-testing-strategy.md](17-testing-strategy.md#coverage-notes) for how testing accommodates it.

## Phase 8 — Investigation Notes

Delivered as part of Phase 5's Investigation Workspace milestones (the Engineering Notebook), not as a separately-numbered phase in the actual build sequence — the original 12-phase roadmap's Phase 7/8/9 boundaries were absorbed into Phase 5's 7 milestones during implementation. See [19-milestones.md](19-milestones.md) for exactly where notebook autosave landed.

## Phase 9 — Diagnosis Submission

Delivered as Phase 5, Milestone 6 (see above) — folded into the Student Engineering Office phase rather than kept separate, since the diagnosis form is inseparable from the workspace it's submitted from.

## Phase 10 — Evaluation Engine

Delivered as Phase 6 (see above) — the roadmap's planned phase numbering and the actual delivery sequence diverge here in naming only; the scope described for "Phase 10" in the original 12-phase roadmap is exactly what Phase 6 shipped.

## Phase 11 — Analytics & Performance Dashboard

Delivered as Phase 6, Milestones 3–4 (see above).

## Phase 12 — Testing & Deployment (final Version 1 phase, 5 milestones)

**Objective:** close out Version 1 with a full audit pass and production readiness.
**Milestone 1 — Authorization audit:** every route checked against its intended Policy; no Policy gap found, but a real test-coverage gap was found and closed (see [17-testing-strategy.md](17-testing-strategy.md#coverage-discipline)).
**Milestone 2 — Validation and error-state audit:** checked every list-bearing/form page against the UX spec's empty/loading/error-state checklist; two real gaps found and fixed in the hint-unlock JS (a `fetch` chain that never checked `response.ok`, and a failed unlock that silently re-enabled the button with no message); a client-side `maxlength` mirroring the notebook's server-side rule was added, without which pasting past the limit would 422 on every autosave attempt and the retry loop would spin forever on a permanent failure.
**Milestone 3 — End-to-end testing:** `EndToEndWorkflowTest` — three continuous HTTP-level journeys (admin authoring/publishing, student completing a case, two students never leaking into each other's data).
**Milestone 4 — Performance optimization & N+1 audit:** measured real query counts under scale-up on every major page; found and fixed one genuine N+1 (`Admin\DashboardController::needsAttention()`, 2N queries scaling with draft-case count, fixed to a flat 8 queries regardless of draft count) plus one incidental improvement to `AnalyticsService::categoryAggregates()`. Full detail in [23-performance-optimizations.md](23-performance-optimizations.md).
**Milestone 5 — Deployment preparation:** `docs/12-deployment-guide.md` re-verified with a fresh `migrate:fresh --seed`; `DemoDataSeeder` (three fully-populated published demo cases, confirmed idempotent); full manual smoke test via scripted authenticated HTTP requests (25/25 checks passed) covering both the complete student and admin journeys.
**Outcome:** 291 tests passing at close of Version 1.

---

## Version 2 — The AI Discussion Engine (Phases 13–21)

Design frozen before implementation began: `docs/13-ai-discussion-engine-design.md` (the full behavioral/architectural spec) and `docs/14-v2-implementation-roadmap.md` (sequencing into Phases 13–22, following Version 1's exact discipline — one phase per demoable unit, milestone-sized reviewable commits, full suite green after every milestone). See [08-ai-architecture.md](08-ai-architecture.md) for the seven architectural decisions that shape the whole subsystem.

## Phase 13 — Discussion Engine Foundations (Schema, Models, Contracts) — 6 milestones

Two core tables (`discussion_sessions`, `discussion_turns`); the one Version-1-table touchpoint (`cases.discussion_*` additive columns); three core state-machine enums; two Eloquent models; three empty contracts (`LlmClientInterface`, `AiPersonaInterface`, `DiscussionSubjectInterface`) with two supporting value objects; closed with `DiscussionGraphWiringTest`, mirroring `DomainGraphWiringTest`'s role. **Outcome:** 292 tests. Schema, models, and contracts proved wired before any AI/HTTP/UI logic existed on top of them.

## Phase 14 — Provider-Agnostic LLM Client Layer — 6 milestones (one delivered as a later catch-up)

`config/llm.php` + `FakeLlmClient`; `OpenAiCompatibleLlmClient` (serving Ollama/OpenRouter/OpenAI); `AnthropicLlmClient` + `GeminiLlmClient`; `ChainedLlmClient` (the ordered-fallback core); `LlmClientFactory` (the one place "which provider" is decided — closes the operational cost-safety guarantee); `StructuredOutputParser`/`ParsedStructuredOutput`/`TurnClassifier` (Milestone 6, discovered missing and delivered as a catch-up while starting Phase 15 — see [21-problems-and-solutions.md](21-problems-and-solutions.md) for the scheduling-gap story). **Outcome:** 362 tests. Full detail in [13-provider-abstraction.md](13-provider-abstraction.md).

## Phase 15 — Personas & System Prompt Construction — 5 milestones (one delivered as a later catch-up)

Mentor/Interviewer personas + `PersonaResolver`; `CaseAttemptDiscussionSubject`; `SystemPromptBuilder` (+ `TurnClassifier`, delivered together); `LeakageGuard` (Milestone 5, discovered missing via the same kind of scheduling audit that caught Phase 14's gap — checked Phases 13–14 for the same mistake class before raising it, both found genuinely complete). **Outcome:** 374 tests. Full detail in [10-persona-system.md](10-persona-system.md) and [11-prompt-pipeline.md](11-prompt-pipeline.md).

## Phase 16 — DiscussionService & State Machine — 5 milestones

The core orchestration service; `DiscussionAccepted` event + `PrefillDiagnosisFromAcceptedDiscussion` listener (delivered together per explicit instruction); `DiscussionSessionPolicy` (with the documented correction that the frozen design spec referenced a `CaseAttemptPolicy` that doesn't actually exist in the codebase — Version 1 enforces attempt ownership via middleware, not a Policy); five end-to-end flow tests closing the phase. **Outcome:** 405 tests. Full detail in [14-state-machine.md](14-state-machine.md).

## Phase 17 — HTTP Layer: Routes, Controllers, Requests — 4 milestones

The four Discussion routes inside the existing `attempt.owner`-gated group; `DiscussionController` + Form Requests; a full HTTP student journey test; closing the cross-student authorization test gap (mirroring Phase 12's own audit pattern); rate limiting on the messages endpoint (with a route-model-binding-order bug caught and fixed before commit — see [21-problems-and-solutions.md](21-problems-and-solutions.md)). **Outcome:** 427 tests.

## Phase 18 — Investigation Workspace UI — 4 milestones (3 delivered together)

Entry point (Milestone 1, its own commit — including a manual-check mistake self-caught and corrected, see [21-problems-and-solutions.md](21-problems-and-solutions.md)); chat panel, end/accept flow, and unavailable state (Milestones 2–4, committed together as one physically interleaved unit). **Outcome:** 431 tests after Milestone 1; further tests added with Milestones 2–4. Full detail in [09-discussion-engine.md](09-discussion-engine.md).

## Phase 19 — Performance Review Integration & Admin Configuration — 3 milestones

Discussion section on Performance Review; admin case-editor discussion config fields (`CaseModel::$fillable` gains the three discussion columns only at this point); feature tests for both surfaces.

## Phase 20 — Cost-Safety & Observability Hardening — 3 milestones

`fallback_log` persisted end-to-end through the real `DiscussionService` path (not just the factory in isolation); structured application logging on chain exhaustion; the end-to-end paid-tier safety regression test — "the test that protects the never-silently-spend-money invariant for the life of the project."

## Phase 21 — Provider Behavioral Conformance Validation — 4 milestones

The golden-transcript conformance harness (`php artisan discussion:validate-provider`); run for real against Ollama/OpenRouter/Gemini; a real infrastructure bug found and fixed by the run itself (missing `/v1` suffix on the Ollama base URL); results documented in `docs/15-provider-conformance-results.md` and summarized in [13-provider-abstraction.md](13-provider-abstraction.md). **Outcome:** 482 tests — the full-suite count at the close of Version 2's feature work.

## Phase 22 — Documentation, Deployment Update & Release (in progress at time of writing)

LLM provider setup added to the deployment guide; CHANGELOG/README updated to reflect Version 2 as delivered; a wording correction to the Version-1-touchpoint description (the roadmap's binding rule initially named only the additive `cases` columns as the designed touchpoint, omitting that the frozen spec also explicitly designs three UI integration points — corrected without any code change, since the implementation itself was never wrong, only the summary wording). This documentation package (docs/00–39, the formal project report, and the two supervisor-requested files) was produced as part of Phase 22's scope closure.

---

## Post-Phase-22 — Visual Identity Implementation (ongoing)

Not part of the original 22-phase roadmap: following the completion of Phase 22's core deliverables, a formal Design System v1 specification was authored, approved, and is being rolled out incrementally as its own milestone sequence (token foundation, buttons/forms, cards/nav chrome, empty/loading states, Engineering Discussion alignment, icon system, accessibility/responsive audit). See [16-design-system.md](16-design-system.md) and [19-milestones.md](19-milestones.md) for status.
