# 21 — Problems and Solutions

> **Related:** [22-bug-history](22-bug-history.md) · [30-lessons-learned](30-lessons-learned.md) · [18-development-phases](18-development-phases.md)
> **Audience note:** this document was specifically requested in expanded form by the project's academic supervisor, in addition to its place in the standard documentation index. It documents every significant engineering challenge encountered during the project, in a consistent format: Problem, Root Cause, Investigation Process, Alternatives Considered, Final Solution, Preventive Measures, Related Files, Related Phase/Milestone. Entries are grouped by category and, within a category, chronological.

---

## AI Integration, Ollama, and Provider Validation

### 1. Ollama base URL missing the `/v1` suffix

- **Problem:** every request to the locally-configured Ollama tier failed.
- **Root cause:** `config/llm.php`'s Ollama `base_url` default omitted the `/v1` suffix that Ollama's OpenAI-compatible endpoint requires, causing every request to 404.
- **Investigation process:** discovered not by unit testing (which uses `Http::fake()` against a mocked base URL and would never have caught a real-endpoint-shape mismatch) but by the Phase 21 provider conformance harness making genuine HTTP requests against a real, running Ollama instance.
- **Alternatives considered:** none seriously — this was a straightforward configuration correction, not a design question.
- **Final solution:** appended `/v1` to the default `base_url` in `config/llm.php`.
- **Preventive measures:** this is the concrete justification, recorded in [13-provider-abstraction.md](13-provider-abstraction.md) and [17-testing-strategy.md](17-testing-strategy.md), for keeping a real, manually-invoked provider-conformance harness as a permanent tool rather than treating `Http::fake()`-based unit tests as sufficient coverage of the provider layer — a class of infrastructure bug exists that only a real network call against a real endpoint can catch.
- **Related files:** `config/llm.php`.
- **Related phase/milestone:** Phase 21, Milestones 2–4.

### 2. OpenRouter model disqualified on the injection-resistance golden transcript

- **Problem:** during real conformance validation, the OpenRouter tier's tested model (`nvidia/nemotron-nano-9b-v2:free`) leaked sensitive content when the golden-transcript suite specifically attempted a prompt-injection-style extraction.
- **Root cause:** the model's own behavior under adversarial prompting — not a defect in `LeakageGuard` or the fallback chain, both of which functioned correctly around the failure (the leak was *detected*, which is a working safety mechanism; the finding is about the model's tendency to attempt compliance with the injected instruction in the first place).
- **Investigation process:** the behavioral contract in `docs/13-ai-discussion-engine-design.md` §15 defines this exact scenario as a required test case with **zero tolerance** — never averaged against an otherwise-good pass rate.
- **Alternatives considered:** treating the failure as tolerable because the *system's* defense-in-depth (`LeakageGuard`) still caught it before the student saw it was explicitly rejected — the behavioral contract's zero-tolerance rule exists precisely so that "the safety net caught it" is never used to excuse a provider/model whose default behavior is to try leaking in the first place.
- **Final solution:** the model was **not** shipped as a validated `.env.example` default. No code change was needed — the harness did exactly its job: preventing an unsafe default from being recommended.
- **Preventive measures:** the zero-tolerance rule is now explicit, tested policy (`docs/13` §15.3), not a judgment call re-litigated per provider.
- **Related files:** `docs/15-provider-conformance-results.md`, `app/Discussion/Conformance/*`.
- **Related phase/milestone:** Phase 21, Milestones 2–4.

### 3. Empty AI reply bubble in the Engineering Discussion

- **Problem:** manual testing found the AI reply bubble rendering as visibly empty during a real discussion.
- **Root cause:** each provider client's structured-output-parse-failure fallback sets `reply_text` to the raw model output; in real use, a reasoning-style model (OpenRouter's `nvidia/nemotron-nano-9b-v2:free`) spent its entire `max_tokens` budget on hidden reasoning tokens before ever emitting visible content, leaving the raw output genuinely empty.
- **Investigation process:** verified — not assumed — against three independent sources of truth: the real database data, the exact fallback code path, and a live raw request/response capture. This ruled out a frontend rendering bug before any backend code was touched.
- **Alternatives considered:** silently defaulting `reply_text` to something generic on *every* parse failure (rejected — it would mask genuinely useful raw output on non-empty malformed replies, which the pre-existing fallback correctly preserves for debugging and, sometimes, for a still-readable-if-imperfect student-facing message); switching providers/models immediately (rejected as the *first* response — the underlying architecture-level cause needed to be understood first, since simply hiding the symptom by switching providers wouldn't explain whether other providers had the same latent issue).
- **Final solution:** an `EMPTY_REPLY_PLACEHOLDER` constant added to all three provider clients; only the `reply_text` line in each existing parse-failure fallback changed — a truly empty (post-`trim()`) raw reply becomes *"The AI's reply couldn't be read this round."* A non-empty raw reply, even malformed JSON, is unaffected.
- **Preventive measures:** regression tests per client for both a truly-empty-reply case and a whitespace-only case (proving the `trim()`-based check, not a naive `=== ''` check, is what's exercised); the pre-existing non-empty "unparseable reply" test was re-run unmodified to confirm the non-empty path stayed untouched. See also entry 4 below for the follow-up that confirmed the scope of the underlying cause.
- **Related files:** `OpenAiCompatibleLlmClient.php`, `AnthropicLlmClient.php`, `GeminiLlmClient.php`.
- **Related phase/milestone:** post-Phase-22 maintenance fix, 2026-08-02 (commit `a0d889a`).

### 4. qwen2.5-coder (reasoning-style) vs. qwen2.5 (non-reasoning) comparison

- **Problem:** was the empty-reply defect (entry 3) a general Ollama-integration defect, or specific to one model's architecture?
- **Root cause investigation:** an isolated follow-up experiment installed and tested `qwen2.5:7b`, a non-reasoning chat model, against the **unmodified** implementation (i.e., without the placeholder fix from entry 3 applied, to isolate the variable).
- **Alternatives considered:** assuming the fix in entry 3 was sufficient and skipping direct confirmation of the root cause (rejected — treating a symptom fix as proof of root cause without checking is exactly the kind of unverified assumption this project's standing testing discipline rejects).
- **Final solution/finding:** the empty-content behavior was confirmed specific to reasoning-model architecture (models that consume their token budget on hidden reasoning before visible output), not a general defect in the Ollama integration path itself.
- **Preventive measures:** the local Ollama default model was separately updated to a non-reasoning model, tracked as a gitignored `.env` change outside the code fix itself — a deliberate operational choice, not a code change, since `.env` values are machine-specific and not committed.
- **Related files:** none (configuration-only, gitignored).
- **Related phase/milestone:** post-Phase-22 maintenance fix, follow-up to commit `a0d889a`.

---

## Structured Output and the Discussion Engine

### 5. Two "closed" phases that weren't actually finished

- **Problem:** Phase 14 was declared closed after five milestones, and Phase 15 after three, but each phase's own roadmap document specified more milestones than were actually delivered — `StructuredOutputParser` (Phase 14's real Milestone 6) and `LeakageGuard` (Phase 15's real Milestone 5) were both missing.
- **Root cause:** a scheduling/tracking gap — milestone numbering in the roadmap document didn't match the milestone count actually executed and checked off during implementation.
- **Investigation process:** the Phase 14 gap was discovered while starting Phase 15, Milestone 3, when `TurnClassifier`'s strict, inference-free contract needed a real caller enforcing "no defaulting" and none existed. The Phase 15 gap was discovered by a deliberate self-audit performed *specifically because* the first gap had just been found — Phases 13 and 14 were explicitly re-checked for the same class of mistake before Phase 15's gap was raised, and Phase 13 was confirmed genuinely complete.
- **Alternatives considered:** treating the gaps as acceptable since the phases "mostly" worked and moving forward regardless (rejected — an untested, disconnected `LlmClientInterface` consumer for structured output, and no independent leak-detection layer beyond prompt instructions, are both load-bearing safety/correctness components, not optional polish).
- **Final solution:** both missing milestones were built as explicitly-labeled "catch-up" commits, each closing its respective phase for real, with the gap and its discovery documented directly in the commit message rather than silently absorbed into a later phase's scope.
- **Preventive measures:** the self-audit habit ("check adjacent phases for the same mistake class before moving on") is now a standing practice, demonstrated concretely rather than merely stated — see [30-lessons-learned.md](30-lessons-learned.md).
- **Related files:** `StructuredOutputParser.php`, `ParsedStructuredOutput.php`, `TurnClassifier.php`, `LeakageGuard.php`.
- **Related phase/milestone:** Phase 14 Milestone 6 (catch-up), Phase 15 Milestone 5 (catch-up).

### 6. A design-spec reference to a Policy class that doesn't exist

- **Problem:** the frozen AI design spec (`docs/13-ai-discussion-engine-design.md` §10) instructed that `DiscussionSessionPolicy` should mirror "`CaseAttemptPolicy`'s ownership-check shape."
- **Root cause:** `CaseAttemptPolicy` doesn't exist in the actual Version 1 codebase — the spec's reference predates the actual Version 1 implementation, which enforces `CaseAttempt` ownership via `EnsureAttemptBelongsToUser` middleware, not a Policy class.
- **Investigation process:** checked directly before writing any code for Phase 16 Milestone 4, rather than assuming the referenced class existed and searching for it mid-implementation.
- **Alternatives considered:** inventing a new, parallel ownership concept just to have something matching the spec's literal wording (rejected — this milestone's own explicit requirement was "do not duplicate ownership logic").
- **Final solution:** `DiscussionSessionPolicy` reuses the exact same underlying field (`CaseAttempt.user_id`) via the session's polymorphic `discussable` relation, expressed as a Policy because that's what this specific milestone called for (Policies express the owner-vs-owner-or-admin/instructor distinction that middleware alone can't) — the same rule, not a competing one.
- **Preventive measures:** documented explicitly in the commit as a correction to the frozen spec's stale reference, so a future reader doesn't go looking for a `CaseAttemptPolicy` that was never built.
- **Related files:** `app/Policies/DiscussionSessionPolicy.php`.
- **Related phase/milestone:** Phase 16, Milestone 4.

### 7. Rate limiter reading a route parameter before model binding resolved it

- **Problem:** the first test run of the discussion-messages rate limiter failed with "Attempt to read property id on string."
- **Root cause:** the named `RateLimiter` closure (registered in `DiscussionServiceProvider::boot()`) runs **before** route-model binding resolves `{attempt}` in Laravel's middleware ordering, so `$request->route('attempt')` was still a raw string identifier at that point, not a `CaseAttempt` model instance — the naive `$attempt?->id` read assumed the model.
- **Investigation process:** caught by the test suite itself on the very first run, before the milestone was committed — not discovered later in manual testing or production.
- **Alternatives considered:** reordering middleware so model binding runs first (rejected as unnecessarily invasive to shared middleware ordering for a single rate limiter's convenience); ignoring the attempt identifier and rate-limiting per-user only (rejected — it would violate the explicit per-user-*per-attempt* scoping requirement, letting one attempt's heavy usage throttle a student's unrelated attempts).
- **Final solution:** the rate-limiter key-building code handles both possible shapes (a resolved model or a raw identifier) rather than assuming one, since only a stable per-attempt key string is actually needed at that point, not the model itself.
- **Preventive measures:** the fix is exercised by the same rate-limit test that originally caught the bug — the regression protection and the discovery are the same test.
- **Related files:** `app/Providers/DiscussionServiceProvider.php`.
- **Related phase/milestone:** Phase 17, Milestone 4.

### 8. A manual verification step that silently no-op'd

- **Problem:** while manually verifying the Phase 18 Milestone 1 entry-point button, the first attempt to flip a real case's `discussion_enabled` flag via `->update()` had no visible effect.
- **Root cause:** `discussion_enabled` (and its sibling columns) were deliberately **not** in `CaseModel::$fillable` at that point in the project's timeline (Phase 13's own documented decision — nothing was meant to mass-assign these columns until the Phase 19 admin editor gave them a validated, authorized write path). `->update()` silently no-ops on a non-fillable attribute rather than raising an error, which is exactly what created the false impression that the button toggle wasn't working.
- **Investigation process:** this was a mistake in the *manual verification setup itself*, not a defect in shipped code — caught by comparing the expected vs. actual rendered HTML and recognizing the flag genuinely hadn't changed in the database.
- **Alternatives considered:** temporarily adding the column to `$fillable` to unblock the manual check (rejected — this would risk accidentally leaving a premature mass-assignment path in place before the real authorized admin path existed).
- **Final solution:** used `forceFill()` for the one-off manual verification, re-verified the button's presence/absence correctly, then reverted the test data and the case flag back to its original state before finishing.
- **Preventive measures:** this incident is a clean illustration of why the automated tests never hit this: factories bypass mass-assignment guards entirely, so a Form-Request-driven admin flow (Phase 19) was the first real path that needed `$fillable` opened up, and did so deliberately and only then.
- **Related files:** `app/Models/CaseModel.php`.
- **Related phase/milestone:** Phase 18, Milestone 1.

---

## UI Issues

### 9. Admin sidebar not reaching full page height on tall pages

- **Problem:** on admin pages taller than one viewport (Case Edit, Analytics), the sidebar's dark background stopped at a fixed `100vh` instead of the page's real height, leaving a visible white gap underneath it.
- **Root cause:** the sidebar's flex item (`.flex-shrink-0` in `layouts/admin.blade.php`) already stretched to the containing row's full height correctly — but nothing *inside* that stretched flex item was filling the stretched height; the inner div had an inline `min-height: 100vh` instead of a percentage tied to its actual parent.
- **Investigation process:** found during manual UI testing (not caught by any automated test, since automated tests don't assert rendered pixel heights).
- **Alternatives considered:** a JS-based height-matching script (rejected — solvable with pure CSS, and a JS solution would need to re-run on every content change/resize); a fixed larger `min-height` value (rejected — not robust to arbitrarily long content).
- **Final solution:** replaced the inline `min-height: 100vh` on the sidebar's inner div with `height: 100%` (resolving against the real, stretched row height at `md`+, where the sidebar is a flex item) plus a `min-height: 100vh` floor, so mobile's `.collapse` dropdown behavior — which has no flex parent, so `height: 100%` correctly falls back to `auto` per the CSS spec — is unchanged.
- **Preventive measures:** manually re-verified live on Dashboard, Analytics, and Case Edit via an authenticated admin session after the fix.
- **Related files:** `resources/sass/app.scss`.
- **Related phase/milestone:** post-Phase-22 maintenance fix (commit `296973f`).

### 10. No way into the Admin Console except typing the URL

- **Problem:** `/admin` had no link anywhere in the student shell nav that every user — including admins — lands on after login (`/dashboard` is unconditionally the student Inbox by design).
- **Root cause:** the nav was built for the student journey and never revisited for the admin-as-a-user case once admin login became a realistic manual-testing path.
- **Investigation process:** found during the same manual UI testing pass as entry 9.
- **Alternatives considered:** redirecting admins to `/admin` directly on login instead of `/dashboard` (rejected — it would change `/dashboard`'s meaning as "unconditionally the student Inbox," a documented, deliberate Phase 5 decision, purely to serve a navigation convenience).
- **Final solution:** added an "Admin Console" link (the existing approved term) to `layouts/navigation.blade.php`, gated on `Auth::user()->hasRole()` for admin/instructor only, matching the `/admin` route group's own middleware — invisible to students. A reciprocal "Student Workspace" link was added to the admin sidebar in a later, separate piece of work.
- **Preventive measures:** 3 new tests in `EngineeringOfficeShellTest` cover the nav-link visibility gating (student: absent; admin/instructor: present).
- **Related files:** `resources/views/layouts/navigation.blade.php`.
- **Related phase/milestone:** post-Phase-22 maintenance fix (commit `296973f`).

### 11. Verifying a design-token CSS refactor introduces zero visual regression

- **Problem:** the Design System v1 token-foundation milestone rewired dozens of previously-hardcoded hex/pixel literals in `app.scss` to CSS custom properties. How can "this is a pure formalization, not a restyle" be verified rather than merely asserted?
- **Root cause consideration:** automated feature tests assert HTML/HTTP behavior, not rendered pixel output — none of the existing 498 tests could catch a subtle color or spacing drift introduced by a token substitution mistake.
- **Investigation process:** a real before/after visual comparison — `git stash` on just the two changed SCSS files, rebuild assets, screenshot the evidence viewer/hint rows/Engineering Discussion panel/workspace shell via browser automation; restore the changes, rebuild, and re-screenshot the identical views.
- **Alternatives considered:** trusting the code-review-level confidence that each substitution was a literal value-for-value swap without a live render check (rejected for a change explicitly promised as "zero visual change," given the explicit request to confirm it).
- **Final solution:** confirmed pixel-identical rendering across every checked surface before proceeding to the next milestone (button/form-control styling).
- **Preventive measures:** the same before/after screenshot technique is the template for verifying future token-layer changes claimed to be non-visual.
- **Related files:** `resources/sass/_variables.scss`, `resources/sass/app.scss`.
- **Related phase/milestone:** Post-Phase-22 Visual Identity rollout, Milestone 1.

---

## Deployment and Environment

### 12. Two MySQL-compatible servers on the same development machine

- **Problem:** the local development machine has both a standalone MySQL 8.0 Windows service (port 3306, credentials unknown, unused) and XAMPP's own MariaDB 10.4 instance (reconfigured to port 3307).
- **Root cause:** two independently-installed database services on one machine, a pre-existing environment condition rather than a defect introduced by this project.
- **Investigation process:** identified and documented explicitly in the CHANGELOG's Known Issues section rather than left as an undocumented "works on my machine" gotcha.
- **Alternatives considered:** removing one of the two installed services (rejected — out of scope for an application-level project, and risked breaking other local tooling that might depend on the pre-existing MySQL service).
- **Final solution:** local development explicitly uses the XAMPP MariaDB instance on port 3307; `.env` (gitignored, machine-specific) is configured accordingly, while `.env.example` documents the generic `mysql`/port-3306 default for other contributors' more typical single-database-service setups.
- **Preventive measures:** documented in the CHANGELOG and in [25-deployment-guide.md](25-deployment-guide.md)/[27-developer-guide.md](27-developer-guide.md) so a new contributor isn't surprised by a connection failure against the "obvious" default port.
- **Related files:** `.env.example`.
- **Related phase/milestone:** Phase 1 (ongoing known issue).

### 13. Composer advisory-block policy rejecting valid Laravel releases

- **Problem:** Composer's advisory-block policy rejected every Laravel 11.31–11.55 release at install time.
- **Root cause:** three medium/high-severity security advisories affecting that version range, with fixes only published in Laravel 12.60+/13.10+ at the time — versions well ahead of this project's targeted Laravel 11 line.
- **Investigation process:** confirmed the advisories' actual applicability and severity before deciding on an override, rather than blindly suppressing the check.
- **Alternatives considered:** upgrading to Laravel 12/13 immediately (rejected — a major-version upgrade mid-project for advisories not judged to materially affect this application's actual attack surface would be a disproportionate response); leaving the block in place and being unable to install dependencies (not viable).
- **Final solution:** overrode via `config.policy.advisories.block` in `composer.json` to allow installation, with the decision tracked explicitly as an open item rather than silently worked around.
- **Preventive measures:** flagged in the CHANGELOG's Known Issues section for future revisit — a maintenance/upgrade task, not a closed decision. See [28-maintenance-guide.md](28-maintenance-guide.md).
- **Related files:** `composer.json`.
- **Related phase/milestone:** Phase 1 (ongoing known issue).

### 14. Live browser verification blocked by a database service being down

- **Problem:** while attempting a live visual check of a CSS-only change, `php artisan serve` returned HTTP 500 on every page.
- **Root cause:** the local MySQL/MariaDB service simply wasn't running at that moment — unrelated to the code change being verified (confirmed by checking `storage/logs/laravel.log`, which showed `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it`).
- **Investigation process:** distinguished the environment issue from a code defect by confirming the automated test suite (which runs against in-memory SQLite, independent of the local MySQL service) was unaffected and still passing.
- **Alternatives considered:** proceeding without a live visual check and reporting the change as verified by code review and test-suite results alone (rejected initially, then explicitly flagged to the requester as a limitation, rather than silently treating a code-level confidence check as equivalent to a real render check).
- **Final solution:** reported the blocker honestly, and completed the live check once the database service was confirmed running.
- **Preventive measures:** reinforces the value of the SQLite-based, service-independent test database ([03-non-functional-requirements.md](03-non-functional-requirements.md)) — the automated suite's health was never in question even while the interactive application was unreachable.
- **Related files:** none (environment-only).
- **Related phase/milestone:** Post-Phase-22 Visual Identity rollout, Milestone 1.

---

## Testing and Architecture

### 15. A Faker-driven test flake

- **Problem:** during Phase 13 Milestone 4, one unrelated test (`Admin\DashboardTest`'s category-statistics test) failed on a full-suite run.
- **Root cause:** Faker-generated random data occasionally producing a collision or edge-case value in an unrelated test, not caused by the change being committed.
- **Investigation process:** re-ran the failing test in isolation (passed immediately) and re-ran the full suite twice more (passed both times) before concluding the failure was a pre-existing flake unrelated to the current change, rather than assuming either "it's fine, ignore it" or "my change broke something unrelated" without checking.
- **Alternatives considered:** seeding Faker with a fixed seed globally to eliminate all flakiness project-wide (deferred — a larger test-infrastructure change out of scope for the milestone in front of us at the time).
- **Final solution:** proceeded with the milestone once the flake was confirmed pre-existing and unrelated; documented the finding in the commit message rather than silently ignoring an anomalous CI-adjacent signal.
- **Preventive measures:** the habit of re-running a suspicious failure in isolation before either dismissing or chasing it is applied consistently across the project.
- **Related files:** `tests/Feature/Admin/DashboardTest.php`.
- **Related phase/milestone:** Phase 13, Milestone 4.

### 16. Test factory colliding with seeded reference data

- **Problem:** a new test for `CaseAttemptDiscussionSubject` tried `EvidenceType::factory()->create(['code' => 'log'])` and failed.
- **Root cause:** `'log'` collides with a row `TestCase`'s base setup already seeds via `EvidenceTypeSeeder` on every `RefreshDatabase` migration — a unique-constraint violation, not a logic bug in the class under test.
- **Investigation process:** traced the failure to the seeder rather than assuming the new class itself was broken.
- **Alternatives considered:** using a different, non-colliding evidence-type code in the factory call (rejected as papering over the real issue — the test should exercise real seeded reference data the same way production code does, not a parallel fabricated evidence type).
- **Final solution:** looked the seeded type up instead of creating a duplicate, matching the pattern `DemoDataSeeder` already used elsewhere for the identical situation.
- **Preventive measures:** reinforced an existing convention (look up seeded reference data, don't re-create it) rather than establishing a new one.
- **Related files:** `tests/Feature/Discussion/CaseAttemptDiscussionSubjectTest.php`.
- **Related phase/milestone:** Phase 15, Milestone 2.

### 17. N+1 query in the admin "needs attention" widget

- **Problem:** `Admin\DashboardController::needsAttention()` scaled its query count linearly with the number of draft cases.
- **Root cause:** the method called `CaseCatalogService::publishInvariantErrors()` once per draft case, and that method itself ran two separate queries internally (`->rubricCriteria()->doesntExist()` and `->rubricCriteria()->sum()`) — 2N queries total, measured at 18 queries with 5 drafts and still 18 at 15 (i.e., confirmed to actually be scaling, not merely suspected).
- **Investigation process:** a dedicated, planned audit (Phase 12 Milestone 4) using `DB::enableQueryLog()` against seeded data, scaled up specifically to distinguish flat-cost pages from genuinely-scaling ones — not a bug hunt triggered by a user complaint.
- **Alternatives considered:** eager-loading the relation but leaving the two separate query-builder calls in place (rejected — would still run two queries per case even with the relation preloaded, since the original code queried through the builder rather than reading the loaded collection).
- **Final solution:** the service was changed to read the `rubricCriteria` relation collection instead of two separate query-builder calls, with `needsAttention()` eager-loading it once — flat at 8 queries regardless of draft count, verified at both 5 and 15 drafts. This also incidentally reduced the Case Edit page's query count, since its own `_rubric.blade.php` partial reuses the same loaded relation.
- **Preventive measures:** the full suite (291/291, unchanged) plus manual re-rendering of both fixed pages against real seeded data confirmed identical output — a performance fix that changes *how* data is fetched must be verified to return *identical* data, not just fewer queries.
- **Related files:** `app/Http/Controllers/Admin/DashboardController.php`, `app/Services/CaseCatalogService.php`.
- **Related phase/milestone:** Phase 12, Milestone 4. Full detail in [23-performance-optimizations.md](23-performance-optimizations.md).

---

## Cross-Reference

See [22-bug-history.md](22-bug-history.md) for a compact chronological table of every issue above plus a few smaller ones, and [30-lessons-learned.md](30-lessons-learned.md) for the general engineering principles these incidents, collectively, taught.
