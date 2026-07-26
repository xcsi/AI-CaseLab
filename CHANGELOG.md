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
