# 02 — Functional Requirements

> **Related:** [01-project-vision](01-project-vision.md) · [03-non-functional-requirements](03-non-functional-requirements.md) · [05-backend-architecture](05-backend-architecture.md) · [09-discussion-engine](09-discussion-engine.md)
> **Primary source:** `docs/01-business-requirements.md` §5 (Version 1 requirements), extended here with Version 2 (Engineering Discussion) requirements added during Phases 13–21.

Requirement IDs FR1–FR19 are carried over unchanged from the original business requirements document (Version 1). FR20+ are new, added for the AI Discussion Engine (Version 2). Status reflects the requirement's actual implementation state at the time of writing, not aspiration.

## Authentication & Accounts

| ID | Requirement | Status |
|---|---|---|
| FR1 | Users can register, log in, log out, and reset their password. | Implemented (Laravel Breeze) |
| FR2 | Roles: `student`, `instructor`, `admin`, enforced via authorization policies and middleware. | Implemented — `UserRole` enum, `EnsureUserHasRole` middleware, Policies per resource |

## Case Catalog

| ID | Requirement | Status |
|---|---|---|
| FR3 | Students can browse cases (Assigned Incidents), with a guest-accessible preview of one sample case. | Implemented |
| FR4 | Each case has a title, short description, category, difficulty, estimated time, and status (draft/published/archived). | Implemented |

## Case Investigation

| ID | Requirement | Status |
|---|---|---|
| FR5 | A case detail (Incident Briefing) page presents the support ticket and lists available evidence. | Implemented |
| FR6 | Students open each evidence item in a type-appropriate viewer: log viewer, code viewer (syntax-styled), DB snapshot table viewer, API response/JSON viewer, screenshot viewer. | Implemented — dark, monospace "developer tool" rendering for logs/code/JSON |
| FR7 | The system tracks which evidence a student has viewed. | Implemented — `evidence_views` table, `EvidenceViewed` event |
| FR8 | Students can unlock hints; each hint reduces the case's maximum achievable score. | Implemented — `HintUnlockService`, idempotent, transaction-safe, floored at zero |
| FR9 | Students can write free-text investigation notes (Engineering Notebook), autosaved. | Implemented — debounced PATCH autosave |

## Diagnosis & Evaluation

| ID | Requirement | Status |
|---|---|---|
| FR10 | Students submit a structured final report: root cause, proposed fix, confidence level, cited evidence. | Implemented — idempotent submission (a stale resubmission returns the existing diagnosis rather than erroring) |
| FR11 | The system evaluates the submission against a case-specific rubric and produces a score plus per-criterion feedback. | Implemented — pluggable Strategy pattern (keyword match, evidence citation, manual review) |
| FR12 | Students can view past attempts, their evaluation (Performance Review), and the model-solution explanation after submission. | Implemented |
| FR13 | Students can re-attempt a case, policy-configurable per case (`allow_reattempt`). | Implemented |

## Progress & Dashboard

| ID | Requirement | Status |
|---|---|---|
| FR14 | Student dashboard (Inbox) shows current progress, recent activity, and a recommended next case. | Implemented |
| FR15 | Instructor/admin dashboard shows cohort-level statistics and case-level status (draft/published/archived counts, "needs attention" list). | Implemented — `Admin\DashboardController`, `ActivityLogService` |

## Content Administration

| ID | Requirement | Status |
|---|---|---|
| FR16 | Admin can create/edit/publish/archive cases, evidence items, hints, and rubric criteria. | Implemented — full CRUD, publish-invariant enforcement (a case cannot publish without at least one rubric criterion) |
| FR17 | Admin can manage categories. | Implemented. User management is a placeholder screen (`/admin/users`) — see [29-future-roadmap.md](29-future-roadmap.md) |
| FR18 | Admin can view platform-wide analytics: completion rate, score distribution, hint usage, average completion time, re-attempt rate, per-category breakdown. | Implemented — `AnalyticsService`, `Admin\AnalyticsController` |

## Notifications

| ID | Requirement | Status |
|---|---|---|
| FR19 | Users receive in-app notification when an evaluation is ready. | **Not implemented.** Evaluation is synchronous (computed at submission time), so the original rationale for this requirement (an asynchronous/queued evaluation) does not currently apply. Left as a documented gap, not silently dropped. |

## Engineering Discussion (Version 2 — added Phases 13–21)

| ID | Requirement | Status |
|---|---|---|
| FR20 | On a case with `discussion_enabled = true`, a student can start an "Engineering Discussion" with an AI reviewer before submitting a diagnosis. | Implemented — entry point in the Investigation Workspace top bar |
| FR21 | The AI reviewer challenges the student's stated position using one of two personas (Mentor — offers hints after a stall threshold; Interviewer — never offers hints, holds a stricter acceptance bar), configurable per case. | Implemented — see [10-persona-system.md](10-persona-system.md) |
| FR22 | The discussion is turn-based and bounded: it resolves to one of three terminal states — the AI **accepts** the student's position, the student **ends** the discussion unresolved, or the conversation reaches its configured **maximum round count**. | Implemented — `DiscussionService` four-state machine, see [14-state-machine.md](14-state-machine.md) |
| FR23 | An accepted discussion pre-fills the subsequent diagnosis form with the student's final accepted position (read at render time; nothing is written to the `diagnoses` table early). | Implemented |
| FR24 | The AI reviewer must never leak the case's model-solution text verbatim or near-verbatim, regardless of what the student asks. | Implemented — `LeakageGuard`, a non-LLM, deterministic check independent of prompt instructions; see [15-security-architecture.md](15-security-architecture.md) |
| FR25 | If no LLM provider is reachable, the student sees a neutral "AI Discussion Unavailable" state with a manual retry action — never a hardcoded provider name, never an automatic retry loop, and the normal (non-AI) diagnosis-submission path remains fully usable. | Implemented |
| FR26 | The completed discussion transcript (if any) is visible on Performance Review, and discussion configuration (enabled flag, default persona, max rounds) is editable per case in the admin case editor. | Implemented — Phase 19 |
| FR27 | The platform must support adding new LLM providers/models without changing `DiscussionService`, and must never spend money on a paid provider unless an operator explicitly opts in via configuration. | Implemented — see [13-provider-abstraction.md](13-provider-abstraction.md) and [24-cost-optimizations.md](24-cost-optimizations.md) |

## Out of Scope (by design)

Carried over from the original business requirements (`docs/01-business-requirements.md` §7), still accurate:

- Real AI/LLM-graded free-text diagnosis scoring — rubric-based scoring remains the scoring authority; the Engineering Discussion is adversarial rehearsal, not a grader (see [01-project-vision.md](01-project-vision.md)).
- Live collaborative multi-user case-solving.
- Payment/subscription billing.
- Native mobile app (responsive web only — see [03-non-functional-requirements.md](03-non-functional-requirements.md) for the supported breakpoints).
- Public case marketplace / user-submitted cases.
- Real-time human chat/mentor support.
- Multi-tenancy (single institution/deployment assumed).
