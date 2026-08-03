# 05 — Backend Architecture

> **Related:** [04-system-architecture](04-system-architecture.md) · [07-database-design](07-database-design.md) · [32-api-reference](32-api-reference.md) · [34-class-reference](34-class-reference.md)

## Controllers

Controllers are grouped by audience, matching the routing structure:

```
Http/Controllers/
  Auth/                         (Breeze-generated: login, registration, password reset, email verification)
  ProfileController.php
  Student/
    DashboardController.php     (Inbox)
    CaseCatalogController.php   (Assigned Incidents — index, show)
    CaseAttemptController.php   (start attempt, Investigation Workspace show)
    EvidenceController.php      (record evidence view)
    NotebookController.php      (notebook autosave)
    HintController.php          (hint unlock)
    DiagnosisController.php     (create/store — reads accepted-discussion prefill at render time)
    DiscussionController.php    (start/respond/end/show — see 08-ai-architecture.md)
    PerformanceReviewController.php
  Admin/
    DashboardController.php
    CaseController.php          (resource, except show)
    HintController.php          (nested under cases, shallow resource)
    RubricCriterionController.php (nested under cases, shallow resource)
    CategoryController.php      (resource, except create/show/edit)
    EvaluationReviewController.php (index/edit/update — manual review)
    AnalyticsController.php
```

Every controller follows the same shape: validate via a Form Request, authorize via a Policy or route middleware, delegate to exactly one Service call, return a response. No controller queries Eloquent directly for business data (trivial lookups like `Category::all()` for a dropdown are the one accepted exception, matching the "no Repository for static lookups" rule in [04-system-architecture.md](04-system-architecture.md)).

## Services

See the service index in [04-system-architecture.md](04-system-architecture.md#services-index) and full signatures in [34-class-reference.md](34-class-reference.md). Two service-layer conventions are worth calling out because they were the subject of a dedicated architectural-review pass (Phase 5's post-Milestone-7 review, see [18-development-phases.md](18-development-phases.md)):

- **Services write through Repositories, not Eloquent directly**, for every aggregate that has one (`CaseAttemptService`, `HintUnlockService`, `DiagnosisSubmissionService` all route their writes through `CaseAttemptRepositoryInterface`/`DiagnosisRepositoryInterface`, matching the pattern `CaseCatalogService` established first).
- **Attempt-scoped data is queried through existing Eloquent relations**, not raw `where('case_attempt_id', ...)` lookups — `CaseAttempt::evidenceViews()`/`hintUnlocks()` and `CaseModel::attempts()` are the canonical access paths, adopted across `CaseAttemptController`, `DiagnosisController`, and `CaseCatalogController` during the same review.

## Models

18 Eloquent models under `app/Models/`, one per domain table plus the two Version-2 discussion models (`DiscussionSession`, `DiscussionTurn`). `CaseModel` is named to avoid colliding with the PHP reserved word `Case`; its table remains `cases`. Casts are used extensively for enum columns (`CaseDifficulty`, `CaseStatus`, `AttemptStatus`, `ConfidenceLevel`, `MatchingType`, `DiscussionStatus`, `DiscussionTurnRole`, `DiscussionVerdict`) and JSON columns (`evidence_items.payload`, `evaluation*.metadata`, `discussion_turns.evidence_referenced`/`fallback_log`).

## Repositories

Six interfaces, six Eloquent implementations, one binding provider (`RepositoryServiceProvider`) — see [04-system-architecture.md](04-system-architecture.md#where-a-repository-is-deliberately-not-used) for which aggregates get one and why the trivial lookups (`Category`, `Role`, `EvidenceType`) deliberately don't.

| Interface | Implementation |
|---|---|
| `CaseRepositoryInterface` | `EloquentCaseRepository` |
| `CaseAttemptRepositoryInterface` | `EloquentCaseAttemptRepository` |
| `DiagnosisRepositoryInterface` | `EloquentDiagnosisRepository` |
| `EvaluationRepositoryInterface` | `EloquentEvaluationRepository` |
| `EvidenceItemRepositoryInterface` | `EloquentEvidenceItemRepository` |
| `HintRepositoryInterface` | `EloquentHintRepository` |

`AnalyticsService`'s aggregate methods (`statusCounts()`, `scorePercentages()`, `usageCounts()`, `averageCompletionSeconds()`, `reattemptCounts()`) were added to these same three repositories (`CaseAttemptRepositoryInterface`, `EvaluationRepositoryInterface`, `HintRepositoryInterface`) rather than a new Analytics repository — consistent with "only aggregate roots get one."

## Policies

See [04-system-architecture.md](04-system-architecture.md#policies) for the summary table and [37-security-review.md](37-security-review.md) for the Phase 12 authorization audit that verified every route against its intended Policy.

## Validation

Every write action validates through a Form Request class (`app/Http/Requests/`), never inline `$request->validate()` in a controller. Requests mirror their server-side rules as HTML5 attributes on the corresponding form field (e.g. `maxlength` mirroring `max:`) — a pattern that was retrofitted onto the notebook textarea after the Phase 12 validation audit found it missing there (see [21-problems-and-solutions.md](21-problems-and-solutions.md)). `prepareForValidation()` is used consistently to normalize "empty means absent" cases (a blank select/number input arrives as `""`, not omitted) — first established for `allow_reattempt`, later reused verbatim for `discussion_default_persona`/`discussion_max_rounds`.

## Authorization

Two mechanisms, applied consistently by scope:

1. **Route middleware** for ownership checks with no meaningful nuance — `EnsureAttemptBelongsToUser` (`attempt.owner`) blocks every non-owner from every `/investigation/{attempt}/*` route, including all four Discussion routes, before any controller code runs.
2. **Policies** (`$this->authorize()` or Form Request `authorize()`) for anything with more than one meaningful outcome — e.g. `DiscussionSessionPolicy` distinguishing "can view" (owner + admin/instructor) from "can participate" (owner only), a distinction middleware alone can't express.

## Routing

All application routes live in `routes/web.php` (no separate `admin.php` — the admin surface is one `Route::middleware(['auth','role:admin,instructor'])->prefix('admin')->name('admin.')->group()` block instead). Investigation-Workspace-scoped routes (evidence view, notebook update, hint unlock, diagnosis create/store, all four discussion actions) share one `Route::middleware(['auth','attempt.owner'])->prefix('investigation/{attempt}')->group()` block rather than repeating both middleware and prefix per route. Full route table in [32-api-reference.md](32-api-reference.md).

## Error Handling

Business-rule violations are modeled as typed exceptions, not generic `abort()` calls, so a caller can distinguish *why* an action failed:

| Exception | Meaning | HTTP mapping |
|---|---|---|
| `ReattemptNotAllowedException` | Case's `allow_reattempt` policy blocks a new attempt | 403 |
| `DiscussionAlreadyActiveException` | `start()` called with an existing Active session on the attempt | 409 |
| `DiscussionNotActiveException` | `respond()`/`end()` called on a terminal session | 409 |
| `NoLlmProviderAvailableException` | Every configured LLM tier failed | 503 (generic "AI Discussion Unavailable" message) |
| `LeakedReplyException` | `LeakageGuard` flagged an AI reply before persistence | 503 (identical generic message — a student never learns why) |

Controllers catch exactly the exceptions their own use case can raise and map each to an HTTP status; no controller has a blanket try/catch that obscures which failure occurred.
