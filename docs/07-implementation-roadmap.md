# AI CaseLab — Implementation Roadmap

This roadmap sequences the approved architecture (`docs/03-database-design.md`, `docs/04-architecture.md`, `docs/05-ui-ux-design.md`) into 12 build phases. It supersedes the day-by-day pacing in `docs/06-task-breakdown.md` as the top-level plan; that earlier document remains useful as a finer-grained reference for how a phase can be split into daily sub-steps, but **phases below are the unit of work** — each ends in a demo, and each requires your confirmation before the next begins, per your standing instructions.

Tracked as Tasks #1–#12 (linear dependency chain: each phase is blocked by the one before it).

**Version 1 status: complete (Phases 1–12).** Version 2 (the AI Discussion Engine) continues phase numbering from 13 onward in `docs/14-v2-implementation-roadmap.md`, sequencing the frozen architecture in `docs/13-ai-discussion-engine-design.md`. This document is preserved as-is as the historical record of how Version 1 was built.

---

## Phase 1 — Laravel Project Setup

**Objective:** A running, correctly configured Laravel application with the frontend build pipeline and base layout shell in place — the foundation every later phase builds on.

**Features:**
- Fresh Laravel install, MySQL connection configured and verified.
- Breeze (Blade stack) installed for auth scaffolding — we build on top of it rather than hand-rolling login/register.
- Bootstrap 5 wired through Vite.
- Base layouts (`layouts/app.blade.php` for student shell, `layouts/admin.blade.php` for admin shell) with navigation per the approved UI/UX doc, linking to placeholder routes.
- Git repository initialized with a sensible `.gitignore`.

**Dependencies:** None — starting point.

**Expected Deliverables:** `composer.json`/`package.json` configured; `php artisan serve` + `npm run dev` both work; a styled (not bare-scaffold) home page and login/register pages reachable; admin/student nav shells visible (links can 404, that's expected at this stage).

**Estimated Time:** 2 days.

---

## Phase 2 — Authentication & Roles

**Objective:** Role-based access control layered on top of Breeze auth, so every later phase can gate routes by `student`/`instructor`/`admin` from day one instead of retrofitting it.

**Features:**
- `roles` table/model/seeder (`student`, `instructor`, `admin`).
- `users.role_id` column added to Breeze's migration.
- `EnsureUserHasRole` middleware.
- Route groups: `/admin/*` gated to `admin` (and `instructor` where read-only), student routes gated to authenticated users generally.
- A first-run seeder that creates one admin account for local development.

**Dependencies:** Phase 1.

**Expected Deliverables:** Register/login work end to end; a non-admin hitting `/admin` gets a 403; an admin reaches an (still mostly empty) admin shell. Feature tests covering role gating.

**Estimated Time:** 2 days.

---

## Phase 3 — Database Schema & Models

**Objective:** The complete domain schema from the approved database design, plus the Repository layer scaffolding the whole architecture depends on. This is the single largest structural phase — everything from Phase 4 onward reads/writes through what we build here.

**Features:**
- Migrations + Eloquent models + relationships for all 13 domain tables: `categories`, `evidence_types`, `cases` (soft-deletes), `evidence_items` (JSON payload), `hints`, `rubric_criteria`, `case_attempts`, `investigation_notes`, `diagnoses`, `evidence_views`, `hint_unlocks`, `evaluations`, `evaluation_criterion_results`.
- Seeders for stable reference data (`evidence_types`, sample `categories`).
- Repository interfaces (`app/Repositories/Contracts`) and Eloquent implementations for the domain aggregates identified in the architecture doc (`CaseRepository`, `EvidenceItemRepository`, `CaseAttemptRepository`, `DiagnosisRepository`, `EvaluationRepository`, `HintRepository`), bound via `RepositoryServiceProvider`.
- Model factories for testing.

**Dependencies:** Phase 2 (models reference `users`).

**Expected Deliverables:** `php artisan migrate:fresh --seed` runs clean; a tinker/test script builds one full case graph (case → evidence → hint → rubric → attempt) through the repositories, proving relationships and bindings work before any UI exists on top of them.

**Estimated Time:** 4 days.

---

## Phase 4 — Admin CMS

**Objective:** Give admins a working content-authoring surface so the platform can have real cases before we build anything student-facing — content must exist before there's anything to investigate.

**Features:**
- `Admin\CaseController` CRUD (list, create, edit) with `StoreCaseRequest`/`UpdateCaseRequest` and `CasePolicy`.
- `CaseCatalogService` for create/update/publish orchestration.
- Category management (simple CRUD).
- Hint Manager (ordered list, penalty field).
- Rubric Builder (ordered criteria, live weight-total validation against `max_score`).
- Publish workflow enforcing the invariants agreed in the architecture doc (≥1 evidence item, ≥1 rubric criterion, weights sum correctly) — evidence authoring itself is built in Phase 7, but the publish gate and admin dashboard shell belong here.
- Admin dashboard shell: case data table with status/quick actions.

**Dependencies:** Phase 3.

**Expected Deliverables:** An admin can create a case, add hints, define a rubric, and see why Publish is disabled until evidence exists (evidence added in Phase 7 will unlock it). Feature tests for CRUD + publish invariants.

**Estimated Time:** 5 days.

---

## Phase 5 — Student Engineering Office

**Objective:** The student's home base — dashboard, case catalog, and case details — establishing the "workplace" framing before the student ever opens an investigation.

**Features:**
- Student dashboard (`DashboardController`): stat cards, continue-in-progress card, recent activity — populated from real `case_attempts` data (charts/recommendations can be stubbed simply here; the full analytics treatment is Phase 11).
- Case catalog page: filter/search (category, difficulty, status), case cards, pagination.
- Case details (pre-investigation) page: ticket preview, evidence-type teaser list, scoring/policy blurb, Start/Resume/View-Report CTA logic.
- `CaseAttemptService::start()` (create-or-resume attempt) and `EnsureAttemptBelongsToUser` middleware.

**Dependencies:** Phase 4 (needs at least one published case with hints/rubric to browse and start).

**Expected Deliverables:** A student can log in, browse the catalog, open a case's details, and click Start to create a real `case_attempt`, landing on a placeholder investigation route. Feature tests for catalog filtering and attempt start/resume logic.

**Estimated Time:** 3 days.

---

## Phase 6 — Incident Investigation Workspace

**Objective:** The 3-pane workspace shell that is the product's core screen — built as an empty-but-structurally-complete IDE-like frame before evidence content is wired in, so the layout, navigation, and state machine (viewed/locked, timer, exit) are solid on their own.

**Features:**
- 3-pane layout (Evidence Explorer / Viewer / Notes) per the UI/UX doc, tablet-responsive stacking.
- Evidence Explorer list grouped by type with viewed/locked indicators (viewer content itself is Phase 7).
- Top slim bar: case title, elapsed timer (JS), evidence-viewed progress counter, Submit-Diagnosis entry point (wired fully in Phase 9).
- Exit-confirmation modal; `EnsureAttemptNotAlreadySubmitted` middleware.

**Dependencies:** Phase 5 (needs a real `case_attempt` to attach the workspace to).

**Expected Deliverables:** Opening an attempt lands on a fully laid-out workspace; evidence items are listed and clickable (rendering a placeholder until Phase 7); timer and exit-confirmation work.

**Estimated Time:** 4 days.

---

## Phase 7 — Evidence Management

**Objective:** The evidence subsystem end to end — both the admin authoring side and the student viewing side — since both consume the same `evidence_items`/`payload` design and are naturally built together.

**Features:**
- Admin sub-forms per evidence type (log, code snippet, DB snapshot, API response, screenshot) that build the `payload` JSON from typed fields rather than raw JSON entry; reorder/delete within a case.
- Student-facing viewer components per type, rendered in the Phase 6 workspace's center pane: dark log viewer with level coloring, syntax-highlighted code viewer, DB table viewer, split API request/response viewer, screenshot lightbox.
- `EvidenceInvestigationService::recordView()`, `EvidenceViewed` event, `RecordEvidenceView` listener → populates `evidence_views`.
- Screenshot image upload via Laravel `Storage`.

**Dependencies:** Phase 6 (viewers render inside the workspace built there) and Phase 4 (evidence authoring extends the admin CMS's case editor).

**Expected Deliverables:** An admin can fully populate a case's evidence across all 5 types; a student can open each type in the workspace and see it rendered correctly; view-tracking rows appear in `evidence_views`. This phase also unblocks Publish in Phase 4 (evidence invariant satisfied).

**Estimated Time:** 5 days.

---

## Phase 8 — Investigation Notes

**Objective:** Let students capture their reasoning trail without friction — a small, self-contained feature once the workspace exists.

**Features:**
- `InvestigationNoteController` + `NoteService`.
- Debounced autosave via a lightweight fetch endpoint; Saved/Saving/error indicator.

**Dependencies:** Phase 7 (workspace + evidence must be navigable for notes to be meaningful in context).

**Expected Deliverables:** Notes persist across page reloads and across evidence-tab switches; autosave is visibly responsive. Feature test for note persistence and attempt-ownership check.

**Estimated Time:** 2 days.

---

## Phase 9 — Diagnosis Submission

**Objective:** The structured "close the ticket" action that turns an investigation into a gradable artifact.

**Features:**
- Final report form (root cause, proposed fix, confidence level, cited-evidence multi-select pre-checked from `evidence_views`).
- `DiagnosisController` + `SubmitDiagnosisRequest` + `DiagnosisService::submit()` (persists `diagnoses`, marks attempt `submitted`).
- Hint system finished here if not already covered: locked hint list, penalty-confirm modal, `HintService::unlock()` recalculating `max_possible_score` (natural fit alongside the submission summary sidebar that displays hints used).

**Dependencies:** Phase 8.

**Expected Deliverables:** A student can submit a full diagnosis; attempt state transitions to `submitted`; hint economics are reflected in the recap sidebar. (No scoring yet — that's Phase 10.)

**Estimated Time:** 3 days.

---

## Phase 10 — Evaluation Engine

**Objective:** The pluggable, rubric-driven scoring system — the feature that makes this a training platform rather than a ticket-closing simulator, and the architecture's main proof point for the Strategy pattern.

**Features:**
- `EvaluationStrategyInterface` + `EvaluationStrategyResolver`.
- `KeywordMatchStrategy`, `EvidenceCitationStrategy`, `ManualReviewStrategy` (stub).
- `EvaluationService::evaluate()` orchestration: iterates rubric criteria, resolves strategy per `matching_type`, persists `evaluations` + `evaluation_criterion_results`, marks attempt `completed`, fires `CaseAttemptCompleted`.
- Evaluation result page: score header, per-criterion breakdown, model-solution reveal (respecting `allow_reattempt`), re-attempt/back-to-catalog actions.

**Dependencies:** Phase 9 (needs a submitted diagnosis to evaluate).

**Expected Deliverables:** Submitting a diagnosis produces a real, rubric-based score with per-criterion feedback for all three seeded demo cases. Unit tests for both concrete strategies.

**Estimated Time:** 5 days.

---

## Phase 11 — Analytics & Performance Dashboard

**Objective:** Turn the activity data captured since Phase 3 (`evidence_views`, `hint_unlocks`, `case_attempts`, `evaluations`) into insight for students and instructors — the last functional feature before hardening.

**Features:**
- `AnalyticsService`: completion rate, score distribution, average time-to-complete, hint-usage stats, evidence view balance (never-cited vs. over-relied-on evidence).
- Student dashboard upgrade: recommended-next-case logic, progress-by-category chart (Chart.js).
- Admin analytics page: cohort/case filters, charts and tables; "Needs attention" widget (incomplete drafts + pending manual-review submissions).

**Dependencies:** Phase 10 (analytics are meaningless without completed, scored attempts to aggregate).

**Expected Deliverables:** Both dashboards show real, query-backed numbers against seeded attempt history; admin can filter analytics by case/category.

**Estimated Time:** 4 days.

---

## Phase 12 — Testing & Deployment

**Objective:** Harden and ship — close every gap a graduation-project defense or a real user would find.

**Features:**
- Authorization audit: every route checked against its intended Policy; tests proving cross-user/cross-role access is blocked.
- Validation/error-state audit against the UI/UX doc's checklist (empty/loading/error states everywhere).
- Feature test suite for the three core flows (admin authors a case, student completes a case, evaluation scores correctly) + unit tests for strategies (extends Phase 10's tests).
- Performance pass: apply the indexes from the database design doc, eliminate N+1 queries (verified via query-count tooling) on catalog/dashboard/analytics pages.
- Production deployment: environment config, migrate + seed on target host, smoke test, prepare demo data/script for the defense.

**Dependencies:** Phase 11 — this phase assumes all features exist and hardens/ships them.

**Expected Deliverables:** Green test suite, no N+1 regressions on key pages, a deployed and smoke-tested instance, demo script ready.

**Estimated Time:** 5 days.

---

## Summary Table

| Phase | Name | Depends On | Est. Time |
|---|---|---|---|
| 1 | Laravel Project Setup | — | 2 days |
| 2 | Authentication & Roles | 1 | 2 days |
| 3 | Database Schema & Models | 2 | 4 days |
| 4 | Admin CMS | 3 | 5 days |
| 5 | Student Engineering Office | 4 | 3 days |
| 6 | Incident Investigation Workspace | 5 | 4 days |
| 7 | Evidence Management | 6, 4 | 5 days |
| 8 | Investigation Notes | 7 | 2 days |
| 9 | Diagnosis Submission | 8 | 3 days |
| 10 | Evaluation Engine | 9 | 5 days |
| 11 | Analytics & Performance Dashboard | 10 | 4 days |
| 12 | Testing & Deployment | 11 | 5 days |

**Total: ~44 working days**, organized so each phase ends with something demoable and requires explicit sign-off before the next begins.
