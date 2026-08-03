# 20 — Design Decisions

> **Related:** [18-development-phases](18-development-phases.md) · [31-architecture-decision-records](31-architecture-decision-records.md) · [30-lessons-learned](30-lessons-learned.md)
> This document collects the *why* behind AI CaseLab's major engineering choices in narrative form. [31-architecture-decision-records.md](31-architecture-decision-records.md) presents the same material in strict ADR format (Context/Problem/Alternatives/Decision/Consequences) for the highest-stakes decisions specifically.

## Stack Choices

**Laravel 11 + Blade, not a separate SPA/API frontend.** The application is a server-rendered monolith. A separate frontend framework (React/Vue) would add a build pipeline, a state-management layer, and an API-contract-versioning concern for a product whose interactivity needs (evidence tabs, autosave, a chat panel) are genuinely small and self-contained per page. Blade plus small, page-scoped `fetch`-driven JS delivers the same user-facing behavior with a fraction of the moving parts — directly serving the project's standing "no unnecessary complexity" rule (see [08-implementation-rules.md](08-implementation-rules.md) if migrated, or the process rules in [01-project-vision.md](01-project-vision.md)).

**Bootstrap 5 over Tailwind/Alpine (Breeze's default).** Breeze ships a Tailwind/Alpine frontend by default; it was replaced with Bootstrap 5 at Phase 1. Bootstrap's component library (modals, dropdowns, collapse) covers the admin CRUD surfaces and workspace panels with less custom JS than Alpine would require for the same behavior, and its Sass variable system is what makes the later Design System token layer possible as variable overrides rather than a from-scratch utility framework (see [16-design-system.md](16-design-system.md)).

**MySQL/MariaDB in development and production; SQLite in-memory for tests.** A single production-representative RDBMS avoids dialect-specific query bugs slipping through; an in-memory test database means the automated suite never depends on a locally-running service being up, and runs fast enough that "run in isolation, then run the full suite" (the project's standing testing discipline) stays cheap to do on every milestone.

## Data Modeling Choices

Covered in full, with rationale, in [07-database-design.md](07-database-design.md)'s seven numbered design decisions (polymorphic-shaped evidence, rubric-based not free-text grading, string+enum over native SQL ENUM, lightweight case versioning, citation pivot table over JSON array, generic activity log, polymorphic-but-single-subject discussion sessions).

## Architectural Pattern Choices

**Repository Pattern applied selectively, not uniformly.** Six aggregate roots get a Repository interface + Eloquent implementation; three near-static lookup tables (`Category`, `Role`, `EvidenceType`) deliberately do not. Applying the pattern everywhere would be ceremony without payoff for trivial CRUD; withholding it everywhere would make the six real aggregates' Services untestable without a real database. The dividing line — "real query complexity and business rules" — is a judgment call, stated explicitly rather than left implicit, so a future contributor extending the schema has a rule to apply, not just a precedent to reverse-engineer.

**Strategy Pattern for evaluation, reused for LLM provider selection.** The Evaluation Engine's `EvaluationStrategyInterface` (Phase 6) established the pattern later reused, in shape though not in code, for `LlmClientInterface` (Phase 14) and `AiPersonaInterface` (Phase 15) — the design spec explicitly cites the Evaluation Engine as the precedent for "personas as data + a thin strategy, not hardcoded chatbots." This is a case of one well-tested pattern earning reuse across an entirely different subsystem built months later.

**Events for cross-cutting side effects, not inline calls.** `EvidenceViewed`, `CaseAttemptCompleted`, and `DiscussionAccepted` all fire from the Service that owns the primary action, with listeners handling the secondary effect. This keeps e.g. `EvaluationService` ignorant of dashboard-stat denormalization, and `DiscussionService` ignorant of diagnosis-prefilling — each can be extended (a new listener for badges, a new listener for a different subject type's post-acceptance behavior) without the emitting Service ever changing. `CaseAttemptCompleted` currently has no listener at all — a documented, intentional extension point, not dead code.

## The AI Discussion Engine's Foundational Choices

The single richest set of design decisions in the project; see [08-ai-architecture.md](08-ai-architecture.md#the-seven-architectural-decisions) for the seven that were frozen before any Version 2 code was written, and [13-provider-abstraction.md](13-provider-abstraction.md) for why the cost-safety guarantee is structural rather than a runtime check specifically. Two additional choices worth calling out here:

**Synchronous request/response, deliberately, not streaming.** The whole application is synchronous; introducing a queue worker and Server-Sent Events infrastructure for one feature, on day one, before there's any evidence the synchronous latency (a few seconds per turn, given the `max_tokens` cap) is actually a problem, would be exactly the kind of premature complexity the project's standing rules warn against. Called out explicitly in the design spec as "the first scalability upgrade," not a launch requirement.

**Golden-transcript behavioral conformance testing, kept manually-invoked and separate from the automated suite.** A provider passing its API contract (returns valid JSON, correct HTTP codes) says nothing about whether it actually *behaves* like the persona it's configured as, or reliably refuses to leak the answer. Rather than trying to fold real, costly, non-deterministic LLM calls into the always-green automated suite (which would make the suite flaky and slow, and would spend money on every CI run), conformance validation is its own console command, run deliberately and documented as a point-in-time result (`docs/15-provider-conformance-results.md`) — a real trade-off between "always know current provider behavior" and "keep the automated suite fast, free, and deterministic," resolved in favor of the latter.

## Frontend/Visual Choices

**A dark, monospace "developer tool" visual language for evidence, deliberately, from the start.** Evidence and code viewers were built dark and monospace from Phase 5 onward — not a later restyling — because the product's core pedagogical claim (this is authentic engineering work, not a quiz) is undermined if the evidence looks like quiz content. The Engineering Discussion panel (Phase 18) explicitly reused this same "Night" palette rather than inventing a new chat-bubble design language, for the identical reason.

**A formal, token-based Design System, introduced after roughly a dozen phases of ad hoc (but already reasonably disciplined) component CSS.** The decision to formalize rather than continue page-by-page was made once a clear symptom appeared: the same colors (`#1e1e2e`, `#8890a6`, etc.) were being retyped as literals across multiple files rather than referenced from one source, and no shared spacing/radius/shadow scale existed to keep new components consistent with old ones by construction rather than by developer memory. See [16-design-system.md](16-design-system.md) for the resulting specification and [19-milestones.md](19-milestones.md) for its incremental, verified rollout.

## Process Choices

**Incremental delivery with a hard stop-gate at every milestone.** Never a large, unreviewable diff; every milestone ends with the full suite green (and, for UI work, a manual smoke test) before the next one starts. This is the single most consistently-applied rule across all 22+ phases and is the primary reason the git history and CHANGELOG are detailed enough to write this documentation package from primary sources rather than reconstructed memory.

**No silent redesigns.** When a better approach was found mid-implementation (e.g., the Phase 3 pre-migration schema review, or discovering `CaseAttemptPolicy` didn't actually exist during Phase 16), it was corrected explicitly, with the correction itself recorded in the commit — never swapped in quietly. See [31-architecture-decision-records.md](31-architecture-decision-records.md) for the highest-stakes examples in formal ADR format.
