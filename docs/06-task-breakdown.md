# AI CaseLab — Task Breakdown & Milestones

Each task below is sized to be finishable and demoable in one working day, and ordered so that every day ends with something runnable — never a half-wired feature spanning multiple days with nothing to show. Estimated total: **~40 working days** across 7 milestones. Adjust pace, not order — dependencies matter more than the day numbers.

## Milestone 0 — Project Setup & Foundations (Days 1–3)

- **Day 1 — Environment & skeleton.** Install Laravel (latest LTS-equivalent), configure `.env`/MySQL connection, install Breeze (Blade stack) for auth scaffolding, install Bootstrap 5 via npm + Vite, verify `npm run dev` + `php artisan serve` both work end to end with a styled welcome page.
- **Day 2 — Base layout & navigation shell.** Build `layouts/app.blade.php` and `layouts/admin.blade.php` per the UI/UX doc's navigation spec (student navbar, admin sidebar), with placeholder links, no real pages behind them yet. Add Bootstrap color/utility conventions (difficulty, status, score bands) as reusable Blade components or CSS classes.
- **Day 3 — Roles & auth foundation.** Migration + model + seeder for `roles` (student/instructor/admin); extend Breeze's `users` migration with `role_id`; add `EnsureUserHasRole` middleware; register route groups (`/admin/*` gated). Demo: register a user, manually promote to admin via tinker/seeder, confirm middleware blocks/allows correctly.

## Milestone 1 — Core Domain Schema & Models (Days 4–7)

- **Day 4 — Case & catalog schema.** Migrations + models for `categories`, `cases` (with soft deletes), `evidence_types` (+ seeder with the 5–6 types). Register `CasePolicy` skeleton.
- **Day 5 — Evidence, hints, rubric schema.** Migrations + models for `evidence_items` (json payload), `hints`, `rubric_criteria`. Add model relationships (`Case::evidenceItems()`, `hasMany hints`, etc.).
- **Day 6 — Attempt & activity schema.** Migrations + models for `case_attempts`, `investigation_notes`, `diagnoses`, `evidence_views`, `hint_unlocks`.
- **Day 7 — Evaluation schema + repository scaffolding.** Migrations + models for `evaluations`, `evaluation_criterion_results`. Create all `Repositories/Contracts` interfaces and empty `Eloquent/*` implementations for the core aggregates; bind them in `RepositoryServiceProvider`. Demo: `php artisan migrate:fresh --seed` runs clean; a tinker script creates one full case graph (case → evidence → hint → rubric) end to end.

## Milestone 2 — Admin Content Authoring (Days 8–15)

- **Day 8 — Admin case CRUD (list + create/edit form).** `Admin\CaseController` + `StoreCaseRequest`/`UpdateCaseRequest`, `CaseCatalogService` create/update methods, admin case list table + case editor form (title, category, ticket content, difficulty, estimated time).
- **Day 9 — Admin category & evidence-type management.** Simple CRUD for `categories` (evidence types stay seed-only/read-only in UI, per the "rarely changes" design decision). Demo: admin creates a new category from the UI.
- **Day 10 — Evidence Manager: logs & code snippets.** Sub-forms for adding `log` and `code_snippet` evidence items to a case (payload JSON built from typed form fields, not raw JSON entry). List/reorder/delete evidence within a case.
- **Day 11 — Evidence Manager: DB snapshot, API response, screenshot.** Remaining three evidence type sub-forms, including image upload (Laravel `Storage`) for screenshots.
- **Day 12 — Hint Manager.** Ordered hint list per case, add/edit/delete, penalty field, order-index drag-or-up/down control.
- **Day 13 — Rubric Builder.** Ordered criteria list, weight fields with a live running-total display validated against the case's `max_score`; `matching_type` selector with type-specific `expected_data` mini-form (keywords list for `keyword`, evidence multi-select for `evidence_citation`).
- **Day 14 — Publish workflow & invariants.** `CaseCatalogService::publish()` business rule (must have ≥1 evidence item, ≥1 rubric criterion, weights sum matches `max_score`); wire the "Needs attention" incomplete-draft check into the admin dashboard; disable Publish button in UI when invariants fail, matching backend.
- **Day 15 — Seed real demo content.** Author 3 full cases end-to-end through the admin UI itself (this doubles as UAT of everything built so far): "Login Failure," "API Returning 500," "Database Performance Issue" — each with 4–6 evidence items, 2–3 hints, 3–5 rubric criteria.

## Milestone 3 — Student Catalog & Case Details (Days 16–18)

- **Day 16 — Case catalog page.** `CaseCatalogController@index`, filter/search UI (category, difficulty, status), case cards per UI/UX spec, pagination.
- **Day 17 — Case details (pre-investigation) page.** Ticket preview, evidence-type-icon teaser list, scoring/policy blurb, Start/Resume/View Report CTA logic based on existing `case_attempts`.
- **Day 18 — Start attempt flow.** `CaseAttemptService::start()` (creates or resumes `case_attempts`), `EnsureAttemptBelongsToUser` middleware, redirect into the (still-placeholder) investigation route. Demo: student clicks Start, lands on a stub investigation page with a real `case_attempt` behind it.

## Milestone 4 — Investigation Workspace (Days 19–25)

- **Day 19 — Workspace layout & evidence explorer list.** 3-pane layout shell (per UI/UX doc), left-pane evidence list grouped by type with viewed/locked indicators, no viewers wired yet.
- **Day 20 — Log & code evidence viewers.** Render `log` and `code_snippet` payloads in the center pane (dark monospace panel with log-level coloring; syntax-highlighted code panel). Wire `EvidenceInvestigationService::recordView` + `EvidenceViewed` event + `RecordEvidenceView` listener.
- **Day 21 — DB snapshot & API response viewers.** Table renderer for `db_snapshot` payload; split request/response renderer with collapsible JSON for `api_response` payload.
- **Day 22 — Screenshot viewer + tabbed multi-open behavior.** Image viewer with lightbox; JS to keep multiple evidence tabs open simultaneously in the center pane (progressive enhancement, degrades to single-view navigation without JS).
- **Day 23 — Notes panel with autosave.** `InvestigationNoteController` + `NoteService`, debounced JS autosave via fetch to a lightweight endpoint, Saved/Saving indicator.
- **Day 24 — Hint system.** Locked hint list UI, confirm-penalty modal, `HintService::unlock()` (creates `hint_unlocks`, recalculates attempt's `max_possible_score`), unlocked hint reveal.
- **Day 25 — Workspace polish & elapsed timer.** Top slim bar (title, elapsed time via JS, evidence-viewed progress counter), exit-confirmation modal, tablet-responsive pane-stacking. Demo: a student can fully investigate a seeded case start to finish.

## Milestone 5 — Diagnosis & Evaluation Engine (Days 26–31)

- **Day 26 — Final report form.** `DiagnosisController@create/store`, `SubmitDiagnosisRequest`, form per UI/UX spec (root cause, fix, confidence, cited-evidence multi-select pre-checked from `evidence_views`).
- **Day 27 — Evaluation Strategy interface + KeywordMatchStrategy.** `EvaluationStrategyInterface`, `EvaluationStrategyResolver`, first concrete strategy scoring free-text against `expected_data.keywords`.
- **Day 28 — EvidenceCitationStrategy + ManualReviewStrategy stub.** Second strategy scoring whether required evidence IDs were cited; stub third strategy that returns a "pending" result for instructor review.
- **Day 29 — EvaluationService orchestration.** Wire `DiagnosisService::submit()` → `EvaluationService::evaluate()` → persist `evaluations` + `evaluation_criterion_results`, mark attempt `completed`, fire `CaseAttemptCompleted`.
- **Day 30 — Evaluation result page.** Score header, per-criterion breakdown list, model-solution reveal (respecting `allow_reattempt`), Back-to-Catalog/Re-attempt actions.
- **Day 31 — Re-attempt flow & edge cases.** Handle `allow_reattempt = false` (block new attempt, show past report only), handle abandoned attempts, verify `EnsureAttemptNotAlreadySubmitted` blocks note/evidence/hint routes post-submission. Demo: full loop — browse → investigate → submit → get scored feedback — works for all 3 seeded cases.

## Milestone 6 — Dashboards & Analytics (Days 32–35)

- **Day 32 — Student dashboard.** Stat cards (completed, avg score, streak placeholder), continue-in-progress card, recent activity list.
- **Day 33 — Recommended-next-case logic + category progress chart.** Simple rule-based recommendation, Chart.js progress-by-category visualization.
- **Day 34 — Admin dashboard.** Stat row, cases data table with quick actions, "Needs attention" widget (from Day 14's invariant checks) + pending manual-review submissions.
- **Day 35 — Analytics page.** `AnalyticsService` aggregates (completion rate, score distribution, avg time, hint usage, evidence view balance), rendered as charts/tables with case/cohort filters.

## Milestone 7 — Polish, Hardening, Deployment (Days 36–40)

- **Day 36 — Authorization audit.** Walk every route against its intended Policy; add missing `authorize()` calls; write feature tests asserting a student can't access another student's attempt or an admin route.
- **Day 37 — Validation & error-state audit.** Confirm every form has server-side validation matching client hints; add empty/loading/error states anywhere still missing per the UI/UX doc's checklist.
- **Day 38 — Automated tests pass.** Feature tests for the three core flows (admin authors a case, student completes a case, evaluation scores correctly); unit tests for both evaluation strategies.
- **Day 39 — Performance pass.** Add the indexes from the Database doc, eager-load relationships to eliminate N+1 queries on catalog/dashboard/analytics pages (verify with Laravel Debugbar/Telescope query counts).
- **Day 40 — Deployment.** Environment config for production, run migrations + seeders on target host, smoke-test the full student and admin flows in the deployed environment, prepare demo script/data for the graduation defense.

## Suggested Pace

If working solo part-time (not full 40 working days back-to-back), group by milestone rather than by exact day count: treat each **Milestone** as a sprint of its own, and don't start Milestone *N+1* until Milestone *N*'s "Demo" checkpoint actually works — that checkpoint is the real definition of done, not the day number.
