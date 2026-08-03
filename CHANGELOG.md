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
- **Phase 6, Milestone 3 — Analytics Foundation:** the reusable
  aggregation layer future dashboards/reports will consume — backend
  only, no UI. New `AnalyticsService::completionMetrics()`/
  `scoreDistribution()`/`hintUsage()`/`averageCompletionTime()`/
  `reattemptStatistics()` compute case-completion rates, score-percentage
  buckets, hint-unlock totals, average time-to-completion, and
  re-attempt rates purely from existing `case_attempts`/`evaluations`/
  `hint_unlocks` data — no new scoring or grading logic. Every method
  takes the same optional `?array $caseIds` scope (null = platform-wide,
  one ID = single case, several = a rollup), so `categoryAggregates()`
  produces category-level numbers by calling the identical `summary()`
  composition per category's case IDs instead of a separate
  implementation — satisfies "do not duplicate query logic" by
  construction rather than by convention. Backing aggregate methods
  (`statusCounts()`, `scorePercentages()`, `usageCounts()`,
  `averageCompletionSeconds()`, `reattemptCounts()`) were added to the
  three repositories that already own this data
  (`CaseAttemptRepositoryInterface`, `EvaluationRepositoryInterface`,
  `HintRepositoryInterface`) rather than introducing a new repository —
  consistent with the architecture doc's rule that only aggregate roots
  get one. `averageCompletionSeconds()` computes the duration in PHP
  (Carbon `diffInSeconds`) instead of a raw-SQL `TIMESTAMPDIFF`, so it
  behaves identically on MySQL (dev/prod) and the SQLite in-memory test
  database. No controllers, routes, or views were touched.
- **Phase 6, Milestone 4 — Analytics Dashboard:** the read-only Admin
  Console page over Milestone 3's `AnalyticsService` — a pure rendering
  layer, no new queries or calculations. New `Admin\AnalyticsController`
  (one `index()` action, `$this->authorize('viewAny', CaseModel::class)`
  reusing `CasePolicy` like `DashboardController` rather than adding a
  policy for a view with no resource of its own) calls
  `AnalyticsService::summary()` and `::categoryAggregates()` and hands
  the arrays straight to the view. The `/admin/analytics` route (gated
  `role:admin,instructor`, wired since Phase 1 as a placeholder) now
  points at the controller instead of the placeholder closure. The view
  covers every metric named in the milestone — completion statistics,
  score distribution, hint usage, average completion time, re-attempt
  statistics, and a per-category breakdown table — as Bootstrap 5 stat
  cards/progress bars/tables matching the existing Dashboard and Manual
  Review pages' conventions. Hints are labeled by ID ("Hint #5") rather
  than content, since enriching them would mean a query beyond what
  `AnalyticsService` already returns. No reporting, export, or AI
  insights — those stay out of scope for a later milestone.
- **Roadmap Phase 12, Milestone 1 — Authorization audit:** every route in
  `routes/web.php` checked against its intended Policy. Every admin
  write action authorizes either through a `FormRequest::authorize()`
  delegating to a Policy (`Store`/`Update*Request` classes) or a
  controller-level `$this->authorize()` call (`destroy`/`publish`/
  `moveUp`/`moveDown`, which have no FormRequest); every student
  attempt-scoped route is protected by the `attempt.owner` middleware
  plus explicit cross-case `abort_unless` checks
  (`EvidenceController::recordView()`, `Student\HintController::unlock()`).
  No Policy gaps were found — the audit's actual finding was a test-
  coverage gap: `CaseManagementTest`, `CategoryManagementTest`,
  `HintManagementTest`, `RubricCriterionManagementTest`, and
  `ManualReviewTest` asserted `student`/`instructor` were forbidden on
  write actions but never asserted a guest is redirected, relying
  implicitly on the `/admin` route group's `auth` middleware without
  proving it per controller. Added the missing guest-redirect
  assertions (12 new tests) so every audited route now has explicit
  guest/student/instructor(where applicable) coverage rather than
  inferring it from the route group. No application code changed —
  audit-only, no new features.
- **Roadmap Phase 12, Milestone 2 — Validation and error-state audit:**
  checked every list-bearing/form page against the UI/UX doc's §11
  checklist ("Empty/loading/error states are designed for every
  list-bearing page"). Nearly everything already matched the spec
  (Publish-disabled-with-tooltip, live rubric-weight preview, inline
  `@error` validation with modal-reopen-on-`old()` across every admin
  CRUD form, empty states on every table/list, the notebook's existing
  saved/saving/error-saving indicator). Two real gaps found and fixed,
  both in the Investigation Workspace's hint-unlock JS
  (`investigation/show.blade.php`): (1) the `fetch(...).then(r =>
  r.json())` chain never checked `response.ok`, so a non-2xx response
  would still be parsed and treated as a successful unlock instead of
  surfacing an error; (2) a failed unlock silently re-enabled the
  button with no message, leaving the student unsure what happened.
  Fixed by checking `response.ok` before parsing and adding a
  previously-missing error message in the confirm modal, mirroring the
  error-state pattern the notebook autosave already used. Also added a
  client-side `maxlength="20000"` to the notebook textarea, mirroring
  its server-side `max:20000` rule the same way every other form field
  in the app already mirrors its FormRequest rule as an HTML5
  attribute — without it, pasting past the limit would 422 on every
  autosave and the retry loop would spin forever on a permanent
  failure it could never fix by retrying. No new pages, no new
  features — validation/error-state fixes only, verified manually by
  forcing the fetch to fail in-browser and confirming the error message
  appears and the hint stays locked.
- **Roadmap Phase 12, Milestone 3 — End-to-end testing:** new
  `EndToEndWorkflowTest` adds three continuous, HTTP-level tests for the
  roadmap's named core flows — an admin authoring and publishing a case
  through every real endpoint (category → case → publish-blocked →
  hint → rubric → publish-succeeds → visible in the catalog); a student
  completing a case through every real endpoint (browse → details →
  start → workspace → evidence view → notes → hint unlock → submit →
  performance review), asserting the final score correctly composes
  rubric matching (keyword + evidence-citation, 25 raw) with the hint
  penalty ceiling (capped to 20); and two students independently
  completing the same case, asserting neither can reach the other's
  workspace/notes/review and neither's page leaks the other's name or
  diagnosis text once both are viewing a real, populated case-average.
  These complement rather than replace the ~280 existing narrower
  per-controller tests — nothing else in the suite previously proved
  that one step's real HTTP response/redirect actually satisfies the
  next step's precondition across the whole journey. Also added the
  one genuinely untested `KeywordMatchStrategy` branch (no keywords
  configured on a criterion → full credit, matching
  `EvidenceCitationStrategy`'s already-tested empty-required-ids
  behavior) to `EvaluationEngineTest`. No new features, no unrelated
  refactoring — evidence authoring still has no admin UI (Phase 7 was
  never built), so these tests attach evidence via factory like every
  other test in the suite already does. Full suite: 291/291 passing.
  Manually re-ran the student-completion flow's service calls directly
  against the real MySQL dev database (transaction rolled back after)
  to confirm the score-capping arithmetic matches the SQLite test
  results exactly.
- **Roadmap Phase 12, Milestone 4 — Performance optimization & N+1 audit:**
  measured real query counts (via `DB::enableQueryLog()` against seeded
  data, scaled up to confirm flat vs. scaling behavior) on every major
  page — Admin Dashboard, Analytics, Cases index/edit, Categories index,
  Evaluations index, student Catalog/Dashboard/Workspace/Performance
  Review. Found and fixed one genuine N+1: `Admin\DashboardController::
  needsAttention()` called `CaseCatalogService::publishInvariantErrors()`
  once per draft case, and that method ran 2 queries internally
  (`->rubricCriteria()->doesntExist()` + `->rubricCriteria()->sum()`) —
  2N queries that scaled with the number of drafts (measured 18 queries
  at 5 drafts, still 18 at 15). Fixed by having the service read the
  `rubricCriteria` relation collection instead of two separate query-
  builder calls, and eager-loading it once in `needsAttention()`'s
  query — now flat at 8 queries regardless of draft count (verified at
  5 and 15). This also incidentally reduced the Case Edit page's query
  count, since `_rubric.blade.php`'s own `$case->rubricCriteria` access
  now reuses the same loaded relation instead of a separate lazy query.
  Also eager-loaded `Category::with('cases:id,category_id')` in
  `AnalyticsService::categoryAggregates()`, replacing one
  `->cases()->pluck('id')` query-builder call per category with a
  single batched query (57 → 51 queries at 5 categories). The remaining
  per-category cost (`summary()` re-running its 5 scoped metric
  queries for each category) is an intentional, documented Milestone-3
  tradeoff — trading query count for zero duplicated aggregation logic
  across platform/single-case/category scopes — and wasn't touched, since
  restructuring it into batched cross-category queries would be a
  substantial rework of that service, not a "safe" optimization, and
  category counts are small in practice. Every other audited page
  (Catalog, both Dashboards, Workspace, Case/Categories/Evaluations
  indexes, Case Edit, Performance Review) was already correctly
  eager-loaded, confirmed flat under 3x scale-up. Also confirmed every
  index named in `docs/03-database-design.md` §4 ("Indexing Notes") is
  already present in the migrations — nothing was missing there.
  No behavior changes — the two touched methods return identical data,
  verified by the full suite (unchanged, 291/291 passing) and by
  re-rendering both fixed pages in-browser against real seeded data to
  confirm identical output.
- **Roadmap Phase 12, Milestone 5 — Deployment preparation & production
  readiness (final roadmap milestone):** `docs/12-deployment-guide.md`
  covering server requirements, environment configuration, build/deploy
  steps, and migration/seeding — re-verified for real with a fresh
  `php artisan migrate:fresh --seed` (23 migrations, zero errors) plus
  the new `DemoDataSeeder` (three fully-populated, published cases —
  "API Returning 500 on Checkout," "Login Failures After Password
  Reset," "Dashboard Queries Timing Out" — confirmed idempotent via a
  second run producing identical counts). Executed a full manual smoke
  test as scripted authenticated HTTP requests against a live
  `php artisan serve` instance (browser automation wasn't available in
  this environment) covering the complete student journey (register →
  catalog → case detail → start attempt → workspace → evidence view →
  notebook autosave → hint unlock → diagnosis submit → scored
  Performance Review → Work History) and admin journey (login →
  Dashboard → Cases → Categories → Analytics → Evaluations manual-review
  queue → opened and saved a manual review), plus guest-redirect
  negative checks — 25/25 checks passed, no application code changed to
  make it pass (three script mistakes were corrected against the real
  route/JS/request-field names instead). Added an explicit Production
  Deployment Checklist and Release Checklist to the deployment guide.
  No business-logic changes; full suite unchanged at 291/291 passing.

- **Version 2 — AI Discussion Engine, Phases 13–21 complete:** implements
  `docs/13-ai-discussion-engine-design.md` in full, per the build sequence
  in `docs/14-v2-implementation-roadmap.md`. A Socratic AI reviewer
  (Mentor/Interviewer personas) that challenges a student's investigation
  before their diagnosis is accepted: `discussion_sessions`/
  `discussion_turns` schema and domain models (Phase 13); a
  provider-agnostic, cost-safe fallback-chain LLM client layer
  (`ChainedLlmClient`/`LlmClientFactory`) defaulting to free/local
  providers and never silently reaching a paid tier (Phase 14); Mentor/
  Interviewer personas, system-prompt construction, and structured-output
  parsing (Phase 15); `DiscussionService`'s four-state state machine
  (Active → Accepted/EndedByStudent/MaxRoundsReached) with an
  accept → diagnosis-prefill event (Phase 16); the HTTP layer — routes,
  controller, Form Requests, rate limiting (Phase 17); the Investigation
  Workspace's "Engineering Discussion" panel — entry point, chat-style
  UI, end/accept flow, and an "AI Discussion Unavailable" state for the
  chain-exhausted case (Phase 18); Performance Review's discussion
  section and admin case-editor configuration fields (Phase 19);
  end-to-end `fallback_log` observability, chain-exhaustion logging, and
  a full-path regression test for the never-silently-spend-money
  guarantee (Phase 20); and a real, evidence-based provider conformance
  validation run against Ollama, OpenRouter, and Gemini free tiers,
  documented in `docs/15-provider-conformance-results.md` (Phase 21).
  Version 1 (Phases 1–12) preserved completely — every touchpoint traces
  to `docs/13`'s own design: the additive, nullable `cases` columns
  (`discussion_enabled`/`discussion_default_persona`/
  `discussion_max_rounds`, §9.3) and the three explicitly-designed UI
  integration points (§11.1–§11.3 — Investigation Workspace, Performance
  Review, Admin Case Editor). Full suite: 482/482 passing. Phase 22
  (Documentation, Deployment Update & Release) is in progress.
- **AI CaseLab Design System v1 — full visual identity rollout, 7
  milestones:** a token-driven visual layer (`resources/sass/_variables.scss`,
  `resources/sass/app.scss`) replacing Bootstrap's stock look app-wide —
  Signal accent, a blue-tinted Slate neutral scale, a Night dark-panel
  scale for the Engineering Discussion, semantic Moss/Amber/Ember colors,
  a tightened radius scale, and hairline-border cards — applied across
  every student and admin screen with no route/controller/service/AI/
  database/state-machine changes. Milestone 1: design tokens. Milestone
  2: buttons and form controls, admin nav "Student Workspace" link.
  Milestone 3: card and nav chrome, a "Skip to content" link on every
  authenticated shell. Milestone 4: a shared `<x-empty-state>` component
  (icon + message + optional action) replacing bespoke empty/loading
  markup. Milestone 5: the Engineering Discussion panel restructured for
  layout, spacing, transcript readability, and header hierarchy per
  designer review (before/after screenshots in `docs/design-review/`).
  Milestone 6: a single outline-stroke SVG icon system
  (`<x-icon>`, `resources/views/components/icon.blade.php`) replacing
  every ad hoc HTML-entity glyph and the hint-lock emoji. Milestone 7: an
  accessibility and responsive audit — two measured WCAG contrast fixes
  (Amber 3.86:1 → 5.28:1 against white; the Signal focus ring on dark
  backgrounds 2.66:1 → 5.86:1), a `:focus-visible` ring extended to four
  custom interactive elements that previously had none, and a code-level
  responsive audit across Desktop/Laptop/Tablet/Mobile with no
  regressions found. A release-candidate QA pass afterward fixed three
  further issues: an empty persona-badge/round-counter chip that
  rendered before an Engineering Discussion began, Bootstrap's unstyled
  default cyan (`bg-info`/`text-bg-info`/`alert-info`) surfacing in the
  Analytics dashboard, the Evidence Explorer's API-response status badge,
  and the Review screen — swapped to the existing Signal token — and the
  admin Case Editor's hint reorder buttons, which still used `&uarr;`/
  `&darr;` entities missed by Milestone 6. New test coverage:
  `SkipLinkTest`, `EmptyStateTest`, `IconSystemTest`,
  `AccessibilityAuditTest`, `AdminNavigationTest`, `BrandingTest`. Full
  suite: 512/512 passing.
- Complete engineering documentation library, `docs/00-executive-summary.md`
  through `docs/39-glossary.md` (40 files, indexed by `docs/README.md`):
  requirements, full system/backend/frontend/database architecture, the
  entire AI Discussion Engine subsystem, security, the approved Design
  System, testing strategy, all 22 development phases and their
  milestones, design decisions, a problems-and-solutions log, bug
  history, performance/cost optimizations, deployment/config/developer/
  maintenance guides, future roadmap, a full engineering retrospective,
  10 formal Architecture Decision Records, an API reference, folder/
  class reference, sequence diagrams, data flow diagrams, a security
  review, an operational runbook, and a glossary — sourced from this
  changelog and the full git history, then verified (every internal
  link and anchor resolves, every Mermaid diagram checked for balanced
  syntax). A separate formal academic report for a university
  supervisor/graduation committee, `docs/AI-CaseLab-Final-Project-Report.md`,
  covers background/objectives/scope/architecture/design decisions/
  chronological development/challenges/testing/results/future work/
  lessons learned/conclusion/references/appendices; its cover page has
  placeholder `[Student Name]`/`[Institution Name]`/`[Supervisor Name]`
  fields to fill in before submission.

### Fixed

- Seeded demo case data: two cases' `ticket_content` had a free-text
  "Priority:" line inconsistent with the `Priority: {Low/Medium/High}`
  badge the Incident Briefing page derives from `cases.difficulty`
  (`incidents/show.blade.php`'s `$priorityLabel`) — "Login Failures
  After Password Reset" (difficulty `easy`) said "Priority: Medium" in
  its ticket text instead of "Low", and "API Returning 500 on Checkout"
  (difficulty `medium`) said "Priority: High" instead of "Medium".
  Corrected in `database/seeders/DemoDataSeeder.php` so the derived
  badge and the ticket's own text always agree; no change to the
  difficulty-to-priority derivation itself.

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
