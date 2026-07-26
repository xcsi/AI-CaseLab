# AI CaseLab — Implementation Rules (Process Contract)

This document is the standing operating procedure for the Implementation Phase. It does not describe the product — see `01`–`07` for that — it describes **how we work** from here on. It is binding until the user changes it.

## 1. Source of Truth

The approved design documents (`03-database-design.md`, `04-architecture.md`, `05-ui-ux-design.md`, `07-implementation-roadmap.md`) are the source of truth. Major components are not redesigned mid-implementation. If a better approach is found while building, it is presented as a **trade-off proposal** (what changes, why, what it costs) before any code changes — never changed silently.

## 2. Incremental Delivery

- No large, multi-feature code drops. Work proceeds **phase by phase** per the roadmap, and within a phase, in reviewable increments.
- A phase is not started until the previous one is confirmed complete by the user.
- If a requested feature depends on a piece that hasn't been built yet, implementation stops and the dependency is explained rather than assumed or stubbed silently.

## 3. Required Structure for Every Implementation Step

Every step that introduces code includes, in this order:

1. **Objective** — what this step builds.
2. **Why this step is needed** — how it serves the approved architecture/roadmap.
3. **Files to create or modify** — explicit paths.
4. **Commands to run** — `artisan`, `composer`, `npm`, migrations, etc.
5. **Production-quality code** — full, not pseudocode, following the rules below.
6. **Testing instructions** — how the user verifies it manually and/or via automated tests.
7. **Expected result** — what "working" looks like.

Steps that are pure planning (like this document, or a phase kickoff) omit #5 by design and say so explicitly, per the user's request to approve plans before code.

## 4. Laravel & Code Quality Standards

- **Thin controllers.** A controller method validates (via a Form Request), calls one Service method, and returns a response. No query building, no business rules in controllers.
- **Business logic lives in Services.** One Service per use case or cohesive area (`CaseCatalogService`, `EvaluationService`, etc.), matching `04-architecture.md`.
- **Repositories only where they earn their keep.** Used for the domain aggregates identified in the architecture doc (`Case`, `EvidenceItem`, `CaseAttempt`, `Diagnosis`, `Evaluation`, `Hint`) because they have real query complexity and benefit from an interface boundary (testability, swappability). Trivial lookup CRUD (`Category`, `EvidenceType`, `Role`) uses Eloquent directly through the Model — introducing a repository there would be ceremony with no payoff, and we said so explicitly in the architecture doc.
- **Form Requests for all validation.** No inline `$request->validate()` in controllers for anything beyond the most trivial single-field cases.
- **Policies for all authorization.** No manual `if ($user->role === 'admin')` checks scattered through controllers/views — a Policy method, called via `authorize()` or `@can`.
- **Eloquent relationships used properly** — `hasMany`/`belongsTo`/etc. declared on models and used instead of manual joins/queries wherever the ORM naturally expresses the relationship from `03-database-design.md`.
- **Single Responsibility per class.** If a Service or Controller starts doing two distinct things, it gets split.
- **Consistent naming.** Class, route, and variable names match the vocabulary established in the architecture and database docs (e.g. `CaseAttempt`, not `Attempt` or `Session` interchangeably). See `09-workplace-terminology.md` for the one deliberate exception: **UI copy** may use workplace narrative language while the underlying code keeps precise technical names — that document explains why and draws the line.
- **Every feature is production-ready before moving on** — validated inputs, authorized access, tested behavior, no `TODO` placeholders left in merged code — not "good enough to demo, will harden later."

## 5. Git & Documentation Discipline

Covered in full in `10-git-workflow.md`; summarized here because it's part of the same discipline:

- Small, focused commits using Conventional Commits.
- A milestone (phase) ends with: a suggested commit message, a short changelog entry, and updated docs/README if the implementation changed anything user-facing or architectural.
- No pushes without explicit approval; no force-push/history rewrite ever, unless explicitly requested.

## 6. What This Means Practically for Phase 1 Onward

Each phase kickoff message will restate its Objective/Why/Files/Commands before any code, wait for approval on the plan where the user has asked for it, then deliver code in reviewable chunks with tests and a verification path, then close with the Git/GitHub checklist from `10-git-workflow.md`.
