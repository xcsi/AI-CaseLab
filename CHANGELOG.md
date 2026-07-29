# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/) once
the first release is tagged.

## [Unreleased]

### Added

- Project design documentation: business requirements, SRS, database design,
  application architecture, UI/UX design, and the 12-phase implementation
  roadmap (`docs/01`–`07`).
- Process documentation: implementation rules, workplace-terminology
  glossary, and Git/GitHub workflow (`docs/08`–`10`).
- Repository scaffolding: `.gitignore`, `.editorconfig`, `.gitattributes`,
  `LICENSE` (MIT), `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`,
  GitHub issue and pull request templates.
- **Phase 1 — Laravel Project Setup:** Laravel 11 application merged into
  the repository root; Laravel Breeze (Blade stack) installed for
  auth scaffolding; Bootstrap 5 wired through Vite (Sass entry point,
  Bootstrap JS bundle replacing Alpine.js/Tailwind); student shell
  (`layouts/app.blade.php` + navbar) and admin shell
  (`layouts/admin.blade.php` + sidebar) built per the approved UI/UX
  design, with placeholder routes for not-yet-built pages (case catalog,
  progress, admin dashboard/cases/categories/users/analytics).
- Development environment switched from SQLite to MySQL (`ai_caselab`
  database) once the local server conflict was resolved (see Known
  issues below) — connection verified, migrations run clean.
- **Phase 2 — Authentication & Roles:** `roles` table (`student`,
  `instructor`, `admin`) and `users.role_id` foreign key; `UserRole`
  backed enum as the single source of truth for role names;
  `EnsureUserHasRole` middleware registered as the `role` alias, gating
  `/admin/*` to the admin role; `RoleSeeder` + `AdminUserSeeder` (local
  admin account, skipped outside non-production environments);
  registration refactored onto a `RegisterUserRequest` Form Request and
  `UserRegistrationService` (always assigns the student role) instead of
  inline controller validation. Feature tests cover guest/student/
  instructor/admin access to `/admin` and the registration role default.
- **Phase 3 — Database Schema & Models:** all 15 domain tables from the
  reviewed design (`categories`, `evidence_types`, `cases`,
  `evidence_items`, `hints`, `rubric_criteria`, `case_attempts`,
  `investigation_notes`, `diagnoses`, `diagnosis_evidence_citations`,
  `evidence_views`, `hint_unlocks`, `evaluations`,
  `evaluation_criterion_results`, `activity_log`); five PHP backed enums
  (`CaseDifficulty`, `CaseStatus`, `AttemptStatus`, `ConfidenceLevel`,
  `MatchingType`); Eloquent models with full relationships (including the
  `diagnoses` ↔ `evidence_items` citation pivot); `EvidenceTypeSeeder` +
  `CategorySeeder` for reference data; Repository interfaces + Eloquent
  implementations for the six domain aggregates (`Case`, `EvidenceItem`,
  `CaseAttempt`, `Diagnosis`, `Evaluation`, `Hint`), bound via
  `RepositoryServiceProvider`. A `DomainGraphWiringTest` builds one full
  case graph (case → evidence → hint → rubric → attempt → diagnosis →
  evaluation) through the repositories and asserts every relationship
  resolves both directions, per the roadmap's Phase 3 acceptance
  criteria.
- **Phase 5, Milestone 4 — Hint Unlocking:** Evidence Explorer's Hints
  group now renders each case hint locked with its point penalty and,
  on click, a shared confirm modal ("Ask a senior engineer?") before
  unlocking; `HintUnlockService::unlock()` is idempotent (re-unlocking
  an already-unlocked hint is a no-op) and deducts the hint's
  `score_penalty` from the attempt's `max_possible_score`, floored at
  zero, inside a DB transaction with a row lock; `HintController::unlock`
  (`POST /investigation/{attempt}/hints/{hint}/unlock`, gated by the
  existing `attempt.owner` middleware) rejects hints belonging to a
  different case. Unlocked hints persist and re-render with their
  content on reload via `HintUnlock` records eager-loaded on
  `CaseAttemptController::show`.
- **Phase 5, Milestone 5 — Timer & Progress Tracking:** the Workspace top
  bar's elapsed-time display now ticks live from the attempt's existing
  `case_attempts.started_at`, computed client-side and re-derived correctly
  on every reload since the server timestamp — not client/session state —
  is the source of truth; the evidence-viewed counter (Milestone 2)
  continues to update alongside it. Fixed a latent responsive bug in the
  same top bar surfaced while QAing this milestone: `.min-w-0`/`.min-h-0`
  were used throughout the workspace layout as if they were Bootstrap
  utilities, but Bootstrap 5's default utilities API only ships
  `min-vw-100`/`min-vh-100` — the classes were silently inert. Added real
  `.min-w-0`/`.min-h-0` utility rules and made the top bar wrap
  (`flex-wrap`/`flex-sm-nowrap`) below the `sm` breakpoint, so the timer,
  evidence-viewed counter, and Submit Diagnosis button no longer overflow
  the viewport on narrow screens and the case title truncates correctly
  instead of forcing horizontal scroll.
- **Phase 5, Milestone 6 — Diagnosis Submission:** new Submit Diagnosis
  screen (`GET`/`POST /investigation/{attempt}/report`) — root cause and
  proposed fix textareas, a Low/Medium/High confidence segmented control,
  and multi-select evidence-citation chips pre-checked from whatever the
  student actually viewed (`EvidenceView`), plus a recap sidebar (hints
  used + penalty, time spent, evidence viewed) that becomes a single-column
  layout with a sticky bottom submit bar under `lg`, per the approved UX
  spec. `DiagnosisSubmissionService::submit()` is idempotent — an attempt
  can only ever have one diagnosis, so a stale resubmission (back-button,
  slow double-click) returns the existing one instead of erroring — and
  sets `case_attempts.status = Submitted` with `submitted_at`, leaving
  `completed_at`/`score_earned` untouched since the Evaluation Engine
  (Phase 10) doesn't exist yet. The confirm-before-submit modal's copy
  reflects the case's own `allow_reattempt` policy. The Workspace top bar's
  Submit Diagnosis button, disabled since Milestone 1, now links here.
  Known gap, left for Phase 10: `CaseAttemptService::start()` only blocks
  reattempts against `AttemptStatus::Completed`, not `Submitted`, so a
  student can technically start a second attempt immediately after
  submitting even on a no-reattempt case — resolving it depends on
  Evaluation Engine design decisions out of this milestone's scope.
- **Phase 5, Milestone 7 — Performance Review:** the final screen in the
  student investigation journey, replacing the placeholder at
  `GET /performance-review/{attempt}`. Reads only existing data — no new
  scoring: score header (total/max, color-coded percent badge, optional
  "above/below case average" comparison shown only when another evaluated
  attempt on the same case exists), per-criterion breakdown from
  `EvaluationCriterionResult` (check/partial/cross icon derived from each
  result's already-computed `score_awarded` vs `max_score`, not a new
  algorithm), "What Actually Happened" from `cases.model_solution_summary`,
  and a Back to Incidents / Re-attempt footer that becomes a sticky bottom
  bar on mobile (`incidents.show`'s existing pattern). Re-attempt reuses
  `attempts.store`/`CaseAttemptService::start()` as-is — no new attempt
  logic. When an attempt has a diagnosis but no `Evaluation` row yet (true
  of every real submission right now, since the Evaluation Engine is Phase
  10), shows an "evaluation is still pending" state instead of a score;
  visiting before any diagnosis was submitted redirects back to the
  Workspace instead of a raw error.

### Changed

- **Phase 3 architecture review** (pre-migration): `docs/03-database-design.md`
  revised before any Phase 3 migration was written — enum-like columns
  switched from native SQL `ENUM` to `string` + PHP backed enums;
  `diagnoses.cited_evidence_ids` (JSON) normalized into a
  `diagnosis_evidence_citations` pivot table for analytics performance;
  added `evaluations.metadata` / `evaluation_criterion_results.metadata`
  (nullable JSON) as the future-AI-strategy extensibility seam; added
  `hint_unlocks.penalty_applied` to snapshot the penalty at unlock time;
  added `cases.version` / `case_attempts.case_version` for lightweight
  case-versioning; added a new `activity_log` table for admin/system
  audit trail. `docs/04-architecture.md` updated to list `ActivityLog`
  and `ActivityLogService`.
- **Phase 5 architectural review** (post-Milestone 7, no behavior change):
  full pass over every controller/service/view added across Milestones
  1–7 against SOLID, reusability, route organization, repository usage,
  security, and responsive-behavior criteria — verified via the full test
  suite (222/222 passing before and after) and an identical route table.
  Extracted `App\Support\Badge` (difficulty/score badge classes, was
  duplicated across 4 view files) and `App\Support\ScoreFormatter`
  (trimmed-decimal display, was duplicated across 3 view files).
  `CaseAttemptController`, `DiagnosisController`, and `CaseCatalogController`
  now query attempt-scoped data through `CaseAttempt`'s existing
  `evidenceViews()`/`hintUnlocks()` relations and `CaseModel::attempts()`
  instead of raw `where('case_attempt_id', ...)`/`where('case_id', ...)`
  lookups. `CaseAttemptService`, `HintUnlockService`, and
  `DiagnosisSubmissionService` now route their `CaseAttempt`/`Diagnosis`
  writes through the existing `CaseAttemptRepositoryInterface`/
  `DiagnosisRepositoryInterface` bindings, matching the pattern already
  established by `CaseCatalogService` in Phase 4 instead of calling
  `::create()`/`->update()` on the Eloquent models directly. Grouped the
  six `/investigation/{attempt}/*` routes under one
  `middleware()->prefix()` block instead of repeating both on every
  route. Hardened `investigation/show.blade.php`'s evidence-tab JS to
  build the new tab element via `textContent`/`createElement` instead of
  interpolating the evidence item's title into an `innerHTML` template
  literal (defense-in-depth against a stored-XSS vector if that title
  ever contains markup). Removed a dead-code ternary in the same file
  whose two branches were identical.
- **Phase 6, Milestone 1 — Evaluation Engine (Core):** the Strategy
  pattern from `docs/04-architecture.md` (`EvaluationStrategyInterface` +
  `KeywordMatchStrategy`/`EvidenceCitationStrategy`/`ManualReviewStrategy`
  + `EvaluationStrategyResolver`) plus `EvaluationService::evaluate()`,
  which iterates a case's existing `rubric_criteria`, scores each against
  its own already-defined `expected_data` (keyword list or required
  evidence IDs — no new scoring rules introduced), and persists
  `evaluations`/`evaluation_criterion_results`. Keyword/citation credit
  is proportional (matched ÷ required × weight); manual-review criteria
  are recorded but excluded from the total/max (no instructor workflow
  exists to ever score them, so including their weight would permanently
  under-score any case that uses one) and render on Performance Review as
  "Awaiting instructor review" — the state the UX spec always called for
  but nothing produced until now. The evaluation's ceiling is
  `min(sum of gradable criteria weights, attempt.max_possible_score)`,
  so a hint-penalized attempt's score is still capped correctly.
  `DiagnosisSubmissionService` now calls `EvaluationService::evaluate()`
  synchronously right after submission (`case_attempts.status` reaches
  `Completed` for the first time, with `completed_at`/`score_earned` set
  — retroactively fixing the Inbox's average-score/recent-activity
  widgets, which depended on those columns since Milestone 1 of Phase 5
  but never had them populated). `PerformanceReviewController` also
  evaluates on first view if a diagnosis exists without one yet, so
  every attempt submitted before this milestone shipped gets evaluated
  the next time its Performance Review is opened, rather than staying
  stuck showing "pending" forever. A `CaseAttemptCompleted` event fires
  on completion per the architecture doc's documented extension point,
  with no listener yet (Analytics is out of this milestone's scope).
  Bonus side effect: `CaseAttemptService::start()`'s reattempt gate —
  previously a documented no-op because no attempt ever reached
  `Completed` — is now live, since attempts actually reach that status.
- **Phase 6, Milestone 2 — Manual Review:** the instructor review
  workflow on top of Milestone 1's Evaluation Engine. Two additive
  migrations — `evaluations` gains `reviewed_at`/`reviewed_by`/
  `instructor_comment`; `evaluation_criterion_results` gains
  `instructor_score`/`instructor_comment`. `score_awarded` is never
  overwritten (it stays the auditable strategy output); `instructor_score`
  is a nullable override, and `EvaluationCriterionResult::effectiveScore()`
  is the one place that decides which wins. New `ManualReviewService`
  persists the instructor's per-criterion scores/comments and calls
  `EvaluationService::recalculateTotals()` — extracted from Milestone 1's
  `evaluate()` — so the initial auto-evaluation and a later review recompute
  the same total through the same code, not two implementations. New
  `Admin\EvaluationReviewController` (index/edit/update, thin, delegates to
  the service) behind `EvaluationPolicy` (admin or instructor, unlike
  case-authoring policies which are admin-only) and a new "Reviews" sidebar
  link — one new admin page, not a dashboard redesign. `Evaluation::
  needsInstructorReview()`/`scopeAwaitingInstructorReview()` derive
  "awaiting review" from existing criterion-result data rather than adding
  a redundant status column. Performance Review now shows a reviewed
  criterion's real score/instructor comment instead of the pending state,
  with the stale "Awaiting instructor review" strategy note suppressed
  once a score is in. No approved UX spec exists for this screen (the
  design docs only ever said instructors "may review individual
  submissions," v1 read-mostly) — built consistent with the existing
  Admin Console conventions (Bootstrap cards/tables, `layouts.admin`)
  instead of blocking on a spec that was never produced.

### Known issues

- Composer's advisory-block policy rejects every Laravel 11.31–11.55
  release (three medium/high-severity advisories with fixes only in
  Laravel 12.60+/13.10+); overridden via `config.policy.advisories.block`
  in `composer.json` to allow installation. Tracked as an open decision —
  see the Phase 1 completion notes.
- This dev machine has two MySQL-compatible servers: a standalone
  MySQL 8.0 Windows service on port 3306 (credentials unknown, not
  used), and XAMPP's own MariaDB 10.4 instance reconfigured to port
  3307 (root, empty password — used for local development). `.env` is
  gitignored and machine-specific; `.env.example` documents the generic
  `mysql`/port-3306 defaults for other contributors' setups.
