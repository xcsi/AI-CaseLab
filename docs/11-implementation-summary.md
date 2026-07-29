# AI CaseLab — Implementation Summary (Phases 1–5)

**Status as of:** 2026-07-29
**Branch:** `feature/5-student-engineering-office` (Phases 1–4 already merged to `develop`; Phase 5 approved and complete on this branch, pending merge)
**Purpose:** Reference snapshot of everything built so far, for use as the baseline going into the next phase of work. This document describes what *is*, not what's planned — see [Remaining Roadmap](#remaining-roadmap) for what's still ahead.

> A note on phase numbering before reading further: the original 12-phase plan in `docs/07-implementation-roadmap.md` splits the student experience into five separate phases (5 through 9). In execution, all of that scope was delivered under a single **Phase 5**, broken into seven internal milestones instead of five roadmap phases. The [Roadmap Numbering](#roadmap-numbering-original-plan-vs-actual-delivery) section maps old→actual so nothing is double-counted or lost when planning what comes next.

---

## 1. What Has Been Completed

### Phase 1 — Laravel Project Setup
Laravel 11 application scaffolded at the repo root. Laravel Breeze (Blade stack) installed for authentication scaffolding. Bootstrap 5 wired through Vite (Sass entry point, Bootstrap JS bundle). Two shell layouts built: `layouts/app.blade.php` (student + general, top navbar) and `layouts/admin.blade.php` (admin, sidebar). MySQL configured as the working database.

### Phase 2 — Authentication & Roles
`roles` table (`student`, `instructor`, `admin`) with `users.role_id` foreign key. `UserRole` backed enum as the single source of truth for role names. `EnsureUserHasRole` middleware (aliased `role`) gates `/admin/*` to `admin`/`instructor`. Registration flows through `RegisterUserRequest` + `UserRegistrationService` (always assigns the student role — there is no public "sign up as admin" path).

### Phase 3 — Database Schema & Models
All 15 domain tables (see [§5](#5-database-entities-currently-used)), 5 PHP backed enums, full Eloquent relationship graph, and a Repository layer (interfaces + Eloquent implementations) for the six aggregate roots: `Case`, `EvidenceItem`, `CaseAttempt`, `Diagnosis`, `Evaluation`, `Hint`. A `DomainGraphWiringTest` walks the full case→evidence→hint→rubric→attempt→diagnosis→evaluation graph through the repositories and asserts every relationship resolves both directions.

### Phase 4 — Admin CMS
Full case authoring workflow: Case CRUD, Category management, Hint Manager (with reorder), Rubric Builder, Publish workflow (`CaseCatalogService::publish()` enforcing rubric-weight and evidence-count invariants — the evidence invariant is currently a documented no-op since evidence authoring doesn't exist yet), Admin Dashboard (stat cards, "Needs Attention" list, Recent Activity via `ActivityLogService`).

### Phase 5 — Student Investigation Journey (7 milestones)
The complete student-facing path from login to a closed, reviewed ticket:

| Milestone | Screen | Route |
|---|---|---|
| Shell | Engineering Office global nav | all student pages except Workspace |
| 1 | Inbox | `GET /dashboard` |
| 1 | Assigned Incidents | `GET /incidents` |
| 1 | Incident Briefing | `GET /incidents/{case:slug}` |
| 1 | Investigation Workspace (shell) | `GET /investigation/{attempt}` |
| 2 | Evidence Explorer + Viewer (6 renderer types) | — (in Workspace) |
| 3 | Engineering Notebook (debounced autosave) | `PATCH /investigation/{attempt}/notes` |
| 4 | Hint Unlocking | `POST /investigation/{attempt}/hints/{hint}/unlock` |
| 5 | Elapsed Timer & Progress Tracking | — (in Workspace) |
| 6 | Diagnosis Submission | `GET/POST /investigation/{attempt}/report` |
| 7 | Performance Review | `GET /performance-review/{attempt}` |

Followed by a full architectural review pass (no functional change) — see [§7](#7-security-mechanisms) and [§9](#9-current-project-status) for what it touched.

**Not yet built anywhere in the app:** AI/rule-based evaluation, instructor manual review, analytics dashboards, Work History (still a placeholder page).

---

## 2. Implemented Architecture

**Stack:** Laravel 11, PHP 8.2+, MySQL 8, Bootstrap 5, Vite + light vanilla JS (no SPA framework — server-rendered Blade with progressive enhancement).

**Layering**, consistently applied across both Admin and Student areas:

```
Route → Controller → Service → Repository (aggregate roots only) → Eloquent Model → DB
                         │
                         └→ Form Request (validation) / Policy (authorization, Admin only)
                         └→ Event → Listener (side effects, e.g. evidence view tracking)
```

- **Controllers** are thin: resolve input (Form Requests), call one service method, return a view/redirect/JSON response. No business logic lives in a controller.
- **Services** own business logic and orchestration. A service that only needs simple CRUD writes on an aggregate root routes those writes through its Repository; anything that needs filtering, joins, or aggregation queries the model/relations directly (Eloquent's query builder *is* the read layer — repositories are not re-implemented as generic query builders).
- **Repositories** exist only for the six aggregate roots identified in the Phase 3 design (`Case`, `EvidenceItem`, `CaseAttempt`, `Diagnosis`, `Evaluation`, `Hint`) and expose only `find`/`create`/`update`/`delete`. Reference/lookup data (`Category`, `EvidenceType`, `Role`) and join/audit tables (`HintUnlock`, `EvidenceView`, `EvaluationCriterionResult`) are queried via Eloquent directly — no repository, by design.
- **Policies** gate Admin CRUD (`CasePolicy`, `CategoryPolicy`) via `$this->authorize(...)`. The Student area uses a different, equally deliberate mechanism instead of policies: every attempt-scoped route carries the `attempt.owner` middleware (`EnsureAttemptBelongsToUser`), since "can you touch this resource" reduces to a single ownership check rather than a matrix of role-based permissions.
- **Form Requests** hold all non-trivial validation (`StoreCaseRequest`, `StoreDiagnosisRequest`, etc.). One exception remains by original design: `NotebookController::update()` validates its single nullable field inline rather than via a dedicated Form Request — reviewed and left as-is, since a request class for one line of validation would be pure ceremony.
- **Events/Listeners**: `EvidenceViewed` → `RecordEvidenceView` is the one event in the system, decoupling "the student opened this evidence item" (`EvidenceInvestigationService`) from "here's how that gets persisted" (the listener owns the `evidence_views` upsert/increment logic).
- **Support classes** (`app/Support/`): two small static helpers, `Badge` (difficulty/score→Bootstrap badge-class mapping) and `ScoreFormatter` (trimmed-decimal display), extracted during the Phase 5 review pass to remove duplication that had crept across several Blade views.

**Frontend:** server-rendered Blade, Bootstrap 5 components (modals, offcanvas filters, btn-check segmented controls), small inline `<script>` blocks per page for the interactive bits (evidence tabs, hint-unlock confirm, notebook autosave, elapsed timer) — no bundled JS framework, no client-side routing.

---

## 3. Student Workflow

```
Login → Engineering Office (Inbox) → Assigned Incidents → Incident Briefing
  → Investigation Workspace ⇄ (Evidence Explorer/Viewer, Engineering Notebook, Hints, Timer)
  → Submit Diagnosis → Performance Review
```

1. **Inbox** (`/dashboard`) — "what should I do right now?" Stat row (closed/avg score/streak), Continue-Investigation card if one is in progress, rule-based Recommended-Next card, Recent Activity list. Empty state for first-time students.
2. **Assigned Incidents** (`/incidents`) — filterable/searchable/paginated case catalog. Guest-accessible (unauthenticated visitors see a read-only "sample incident" browsing experience).
3. **Incident Briefing** (`/incidents/{case:slug}`) — ticket preview, evidence-type teaser, scoring/hint/reattempt policy, Start/Resume/View-Report CTA depending on the student's attempt history for that case.
4. **Start/Resume** (`POST /incidents/{case:slug}/start`) — `CaseAttemptService::start()` resumes an existing in-progress attempt or creates a new one, enforcing the case's reattempt policy.
5. **Investigation Workspace** (`/investigation/{attempt}`) — the core screen; global nav is hidden here on purpose. Three panes (Evidence Explorer / Evidence Viewer / Engineering Notebook) collapse to a tabbed single-column layout below `lg`, and to a read-only phone gate below `sm` with an explicit "Continue Anyway" override.
   - **Evidence Explorer/Viewer**: items grouped by type; six renderers (support ticket, log, code snippet, DB snapshot, API response, screenshot) dispatched by `evidence_types.code`; opening a tab records a view (`EvidenceViewed` event) and updates the live X/Y counter.
   - **Engineering Notebook**: freeform textarea, 800ms-debounced autosave, retry-with-backoff on failure, exit-confirmation if a save hasn't landed yet.
   - **Hints**: locked by default with their point penalty shown; unlocking asks for confirmation, then deducts from `max_possible_score` (idempotent — re-unlocking is a no-op).
   - **Timer**: ticks client-side from the attempt's server-recorded `started_at`, so reload/resume always shows the correct elapsed time without its own persistence.
6. **Submit Diagnosis** (`/investigation/{attempt}/report`) — a deliberate separate screen, not another workspace tab. Root cause + proposed fix (required), confidence (Low/Medium/High), evidence citations (pre-checked from what was actually viewed), and a recap sidebar (hints used + penalty, time spent, evidence viewed). Confirm-before-submit modal reflects the case's reattempt policy. Submission is idempotent and moves the attempt to `Submitted` status.
7. **Performance Review** (`/performance-review/{attempt}`) — the final screen. Reads whatever evaluation data exists (see [§9](#9-current-project-status) — there usually isn't any yet), showing score + per-criterion breakdown + "What Actually Happened" (model solution) when it does, or an "evaluation is still pending" state when it doesn't. Back-to-Incidents / Re-attempt footer, sticky on mobile.

---

## 4. Admin Workflow

```
Login (admin/instructor) → Admin Dashboard → Case CRUD → Hint Manager / Rubric Builder → Publish
```

1. **Admin Dashboard** (`/admin`) — stat cards (draft/published/archived case counts), "Needs Attention" (draft cases that would fail publish invariants right now, with the specific reasons), Recent Activity (`ActivityLog`, case-level events only — Category/Hint/Rubric actions aren't wired into the activity log yet), Category breakdown.
2. **Case CRUD** (`/admin/cases`) — full create/edit/delete for a case's core fields (title, slug, summary, ticket content, learning outcomes, difficulty, estimated time, `allow_reattempt`, model solution summary). `CaseCatalogService` bumps `version` on every edit to a *published* case (drafts churn freely) and logs every create/update/publish/archive to `ActivityLog`.
3. **Hint Manager** — add/edit/delete/reorder hints per case (`move-up`/`move-down`), each with its content and point penalty.
4. **Rubric Builder** — add/edit/delete weighted rubric criteria per case, each with a `matching_type` (keyword, evidence citation, or manual review) and its `expected_data`.
5. **Publish** (`POST /admin/cases/{case}/publish`) — blocked with specific, reusable error messages until the case has ≥1 rubric criterion with weights summing above zero. (The "≥1 evidence item" invariant from the architecture doc is intentionally not enforced yet — evidence authoring doesn't exist, so every case would be permanently unpublishable if it were.)

**Not yet built in Admin:** evidence authoring (no UI writes to `evidence_items` at all yet — Phase 4/7 gap, tracked below), Users management (placeholder page), Analytics (placeholder page).

Both `admin` and `instructor` roles can browse everything (`viewAny`); only `admin` can create/update/delete/publish (`CasePolicy`, `CategoryPolicy`) — instructors are read-only by design, per the SRS's content-management split.

---

## 5. Database Entities Currently Used

15 domain tables, all live and populated by real application flows except where noted:

| Table | Populated by | Notes |
|---|---|---|
| `users`, `roles` | Auth (Phase 2) | `role_id` FK; `UserRole` enum is the source of truth for role names |
| `categories` | Admin CMS | reference data, no repository |
| `evidence_types` | Seeder only | reference data, no repository; 6 types map to the 6 evidence renderers |
| `cases` | Admin CMS | `status` (draft/published), `version`, `allow_reattempt`, `max_score` (denormalized sum of rubric weights) |
| `evidence_items` | **Nothing yet** | schema exists (Phase 3), no admin UI writes to it — see gap below |
| `hints` | Admin CMS | content + `score_penalty`, ordered |
| `rubric_criteria` | Admin CMS | weight + `matching_type` + `expected_data` (JSON) |
| `case_attempts` | Student flow | `status` enum: `in_progress` → `submitted` → (`completed`, not yet reachable) / `abandoned` |
| `investigation_notes` | Student flow (Notebook) | one row per attempt |
| `diagnoses` | Student flow (Diagnosis Submission) | unique on `case_attempt_id` — one diagnosis per attempt, enforced at the DB level |
| `diagnosis_evidence_citations` | Student flow | pivot: diagnosis ↔ cited evidence items |
| `evidence_views` | Student flow | one row per (attempt, evidence item), `view_count` increments on every open |
| `hint_unlocks` | Student flow | `penalty_applied` snapshotted at unlock time (not recomputed if the hint's penalty later changes) |
| `evaluations` | **Nothing yet** | schema exists (Phase 3), no writer exists — Evaluation Engine is unbuilt |
| `evaluation_criterion_results` | **Nothing yet** | same — per-criterion scores, read by Performance Review if present |
| `activity_log` | Admin CMS (case-level only) | polymorphic `subject`, generic enough to log any future entity |

**Two tables with schema but no writer**: `evidence_items` and `evaluations`/`evaluation_criterion_results`. This is the single biggest practical gap in the current build — see [§9](#9-current-project-status).

---

## 6. Main Services and Repositories

**Services:**

| Service | Owns |
|---|---|
| `UserRegistrationService` | Registration, always assigns the student role |
| `CaseCatalogService` | Admin case CRUD, versioning, publish-invariant checks |
| `HintService` | Admin hint CRUD + reordering |
| `ActivityLogService` | Recording admin actions for the dashboard's Recent Activity |
| `CaseAttemptService` | Resume-or-start an attempt, enforcing reattempt policy |
| `EvidenceInvestigationService` | Fires `EvidenceViewed` when a student opens an evidence item |
| `HintUnlockService` | Idempotent hint unlock + penalty deduction |
| `DiagnosisSubmissionService` | Idempotent diagnosis submission + attempt status transition |

**Repositories** (interface + Eloquent implementation, bound in `RepositoryServiceProvider`), one per aggregate root: `CaseRepositoryInterface`, `EvidenceItemRepositoryInterface`, `CaseAttemptRepositoryInterface`, `DiagnosisRepositoryInterface`, `EvaluationRepositoryInterface`, `HintRepositoryInterface`. All six are currently thin `find`/`create`/`update`/`delete` wrappers — by design, not a gap; complex queries live in services as direct Eloquent.

**Not yet built:** `EvaluationService` (or equivalent) and any `EvaluationStrategyInterface` implementation — this is the entire scope of the still-unbuilt Evaluation Engine.

---

## 7. Security Mechanisms

- **Authentication**: Laravel Breeze session-based auth, bcrypt password hashing (`BCRYPT_ROUNDS=12`), rate-limited login (Breeze default throttle).
- **Authorization**: two deliberately different mechanisms for two different shapes of problem —
  - **Admin**: Laravel Policies (`CasePolicy`, `CategoryPolicy`) + `role:admin,instructor` route middleware for the `/admin/*` prefix.
  - **Student**: `attempt.owner` middleware (`EnsureAttemptBelongsToUser`) on every attempt-scoped route — a 403 if `case_attempt.user_id` doesn't match the authenticated user. Applied uniformly across all six `/investigation/{attempt}/*` routes plus `/performance-review/{attempt}`.
- **CSRF**: all state-changing requests (forms and `fetch()` calls) carry the Laravel CSRF token; verified in manual QA throughout Phase 5.
- **Mass assignment**: every model's `$fillable` is an explicit allowlist; every write path builds its data array explicitly rather than spreading raw request input.
- **Cross-case/cross-user tampering guards**: hint unlock and evidence-view routes both check the child resource's `case_id` matches the attempt's case before acting (`abort_unless(...)`, 404 on mismatch); `StoreDiagnosisRequest` validates cited evidence IDs belong to the attempt's own case via `Rule::exists(...)->where('case_id', ...)`.
- **XSS**: Blade's `{{ }}` auto-escaping used throughout; the one `{!! !!}` raw-output site (Performance Review's criterion icons) only ever emits one of three hardcoded HTML entities, never user data. One client-side hardening was applied during the Phase 5 review: the evidence-tab JS now builds its DOM via `textContent`/`createElement` instead of interpolating an evidence item's title into an `innerHTML` template string, closing a latent stored-XSS vector.
- **Known, accepted gap**: `User` does not implement `MustVerifyEmail`, so the `verified` middleware on `/dashboard` is currently a no-op (Breeze scaffolding present, not activated) — registration does not require email confirmation to reach the Inbox.
- **Known, documented gap** (flagged, not fixed, in the Milestone 6 changelog entry): `CaseAttemptService::start()` only blocks reattempts against `AttemptStatus::Completed`, not `Submitted`. Since no attempt ever reaches `Completed` yet (Evaluation Engine unbuilt), a student can currently start a second attempt on a no-reattempt case immediately after submitting the first. Resolving this depends on Evaluation Engine design decisions, not something to guess at now.

---

## 8. Testing Summary

**222 tests passing, 568 assertions, 0 failures** (`php artisan test`, in-memory SQLite, ~8–20s depending on machine load).

| Suite | Test classes | Coverage |
|---|---|---|
| Auth | 6 | Registration, login, email verification, password reset/confirm/update (Breeze defaults) |
| Admin | 6 | Case CRUD, publish-invariant workflow, category CRUD, dashboard, hint management, rubric criterion management |
| Student | 9 | Engineering Office shell, Inbox, Assigned Incidents, Incident Briefing, Investigation Workspace shell, Evidence Explorer/Viewer, Hint Unlocking, Engineering Notebook, Diagnosis Submission, Performance Review |
| Domain/Framework | 3 | `DomainGraphWiringTest` (full repository-graph wiring), role gating, profile |

Every Student-area test class covers, at minimum: guest-redirect, cross-user/cross-attempt-ownership rejection (403), and the happy path — this pattern is consistent across all nine Student suites. Feature tests are the only tier in use; there is no dedicated Unit suite beyond the Breeze-generated `ExampleTest` stub, since business logic so far lives in services thin enough to be adequately exercised through feature tests hitting real routes.

---

## 9. Current Project Status

- **Phases 1–5: complete and approved.** Phase 5 additionally passed a full architectural review (SOLID, DRY, route organization, repository usage, security, responsive behavior) with a separate no-functional-change cleanup commit.
- **The student journey is fully walkable end-to-end** except for one thing: **there is no way to add evidence to a case**, and **no evaluation ever runs**. Concretely:
  - A student can log in, browse, start an investigation, view whatever evidence exists (currently none, since Admin has no evidence-authoring UI), take notes, unlock hints, and submit a diagnosis.
  - After submitting, Performance Review always shows "evaluation is still pending" for every real attempt, because nothing writes to `evaluations`/`evaluation_criterion_results` yet.
  - `case_attempts.status` never reaches `Completed`, `submitted_at`/`completed_at`/`score_earned` on the attempt itself are set inconsistently with the Evaluation Engine's intended design (only `submitted_at` is set today).
- **Evidence authoring is a Phase 4/7 gap that was never closed.** The `evidence_items` table and its five typed payload renderers are fully built and tested on the *read* side (Student Evidence Viewer), but nothing on the *write* side (Admin) exists yet. This was deferred, not forgotten — `CaseCatalogService::publishInvariantErrors()` has a `TODO(Phase 7)` marking exactly this.
- **Work History** (`/work-history`) is still the original Phase 1 placeholder page — never implemented.
- Manual QA throughout Phase 5 was performed against a running dev server with hand-seeded fixture data (a "Milestone 4 QA — Checkout 500 Error" case, since no real evidence-authoring path exists to create realistic fixtures through the UI).

---

## 10. Remaining Roadmap

### Roadmap numbering: original plan vs. actual delivery

| Original roadmap phase | Scope | Actual status |
|---|---|---|
| 5. Student Engineering Office | Inbox, catalog, case details, start/resume | ✅ done, inside delivered "Phase 5" |
| 6. Incident Investigation Workspace | 3-pane shell, timer, evidence-viewed counter | ✅ done, inside delivered "Phase 5" |
| 7. Evidence Management | Admin authoring **+** student viewers | ⚠️ **student side done, admin authoring side not built** |
| 8. Investigation Notes | Engineering Notebook | ✅ done, inside delivered "Phase 5" |
| 9. Diagnosis Submission | Report form + hint economics | ✅ done, inside delivered "Phase 5" |
| **10. Evaluation Engine** | Strategy pattern scoring, evaluation result data | ❌ **not started — this is the next functional gap** |
| 11. Analytics & Performance Dashboard | Real dashboards from activity data | ❌ not started |
| 12. Testing & Deployment | Hardening, N+1/perf pass, production deploy | ❌ not started |

The delivered "Phase 5" absorbed the original roadmap's Phases 5, 6, 8, and 9 in full, and Phase 7's *student-facing half only*. **Whatever gets called "Phase 6" going forward should be scoped against this table, not against the original roadmap document's Phase 6**, to avoid re-planning work that's already done or silently skipping the admin evidence-authoring gap.

### Recommended next steps, in dependency order

1. **Close the evidence-authoring gap** (remainder of original Phase 7): admin sub-forms per evidence type, building the typed `payload` JSON — needed before Publish's evidence invariant can be re-enabled, and before any case can be evaluated meaningfully.
2. **Evaluation Engine** (original Phase 10): `EvaluationStrategyInterface` + resolver, `KeywordMatchStrategy` / `EvidenceCitationStrategy` / `ManualReviewStrategy` (stub), an `EvaluationService::evaluate()` that persists `evaluations`/`evaluation_criterion_results` and finally moves `case_attempts.status` to `Completed`. This is what makes Performance Review show real scores instead of "pending" for the first time.
3. **Analytics & Performance Dashboard** (original Phase 11) — meaningless without step 2.
4. **Work History** — a small, previously-deferred Student screen; natural to build alongside or right after step 2, once `Completed` attempts exist to list.
5. **Testing & Deployment hardening** (original Phase 12) — last, once the feature set above is real.

---

*This document reflects the codebase as of the Phase 5 approval. It is a snapshot, not a living spec — re-verify against `git log` and the current schema before relying on it in a future session.*
