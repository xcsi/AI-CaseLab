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
