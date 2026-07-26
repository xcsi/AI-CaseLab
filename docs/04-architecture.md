# AI CaseLab — Application Architecture

## 1. Layering Philosophy

Standard Laravel MVC (Route → Controller → Model → View) is not enough on its own once "evaluate a diagnosis against a pluggable rubric strategy" and "log evidence views without polluting the submission flow" enter the picture. We add two layers between Controller and Model:

```
Route → Middleware → Controller → Service → Repository (interface) → Eloquent Model → MySQL
                          │             │
                     FormRequest   Strategy (evaluation)
                     Policy        Event/Listener (side effects)
                          │
                        View (Blade + Bootstrap) / API Resource (JSON)
```

- **Controller**: HTTP-only concerns — receive request, ensure it's validated and authorized, call one Service method, return a response. No business logic, no direct Eloquent queries.
- **Service**: orchestrates one use case (e.g. `SubmitDiagnosisService`). Talks to one or more Repositories, applies business rules, fires Events. This is where SOLID's Single Responsibility shows up per use case.
- **Repository (interface + Eloquent implementation)**: the only place that talks to Eloquent for a given aggregate. Bound to its interface in a `ServiceProvider` so Services depend on abstractions, not concretions (Dependency Inversion) — swappable/mockable in tests.
- **Strategy**: the Evaluation Engine is a Strategy pattern — one interface, multiple interchangeable scoring algorithms, resolved per rubric criterion's `matching_type`. This is what makes NFR "pluggable evaluation" real rather than aspirational.
- **Policy**: all authorization ("can this user view/edit this case? can this student re-attempt?") lives here, not scattered in controllers/Blade `@if`s.
- **Middleware**: cross-cutting request concerns — auth, role gating, and (later) rate-limiting hint requests.

### Where we deliberately *don't* add a Repository

`Category`, `Role`, `EvidenceType` are near-static lookup tables with trivial queries. Wrapping them in Repository interfaces would be ceremony with no payoff — Eloquent models used directly (via a thin `Category::active()->get()` scope) are fine there. Repository Pattern is applied to the **domain aggregates that have real query complexity and business rules**: `Case`, `CaseAttempt`, `EvidenceItem`, `Diagnosis`, `Evaluation`, `Hint`. This is itself a SOLID judgment call worth defending in a graduation defense: patterns are tools for managing complexity, not a checklist to apply uniformly.

## 2. Folder Structure

```
app/
  Console/
  Enums/
    CaseDifficulty.php
    CaseStatus.php
    AttemptStatus.php
    ConfidenceLevel.php
    MatchingType.php
    UserRole.php
  Events/
    EvidenceViewed.php
    HintUnlocked.php
    DiagnosisSubmitted.php
    CaseAttemptCompleted.php
  Listeners/
    RecordEvidenceView.php
    RecordHintUnlock.php
    UpdateStudentProgressStats.php
  Exceptions/
    CaseAlreadySubmittedException.php
    ReattemptNotAllowedException.php
  Http/
    Controllers/
      Auth/                       (Breeze-generated)
      Student/
        DashboardController.php
        CaseCatalogController.php
        CaseAttemptController.php      (start/show investigation workspace)
        EvidenceController.php         (view a single evidence item)
        InvestigationNoteController.php
        HintController.php
        DiagnosisController.php        (create/submit)
        EvaluationController.php       (show result)
      Admin/
        DashboardController.php
        CaseController.php
        EvidenceItemController.php
        HintController.php
        RubricCriterionController.php
        CategoryController.php
        UserController.php
        AnalyticsController.php
    Middleware/
      EnsureUserHasRole.php
      EnsureAttemptBelongsToUser.php
      EnsureAttemptNotAlreadySubmitted.php
    Requests/
      Student/
        StoreInvestigationNoteRequest.php
        SubmitDiagnosisRequest.php
      Admin/
        StoreCaseRequest.php
        UpdateCaseRequest.php
        StoreEvidenceItemRequest.php
        StoreRubricCriterionRequest.php
    Resources/                    (API/JSON transformers for JS-driven widgets)
      EvidenceItemResource.php
      CaseAttemptResource.php
      EvaluationResource.php
  Models/
    User.php
    Role.php
    Category.php
    CaseModel.php                 (class `CaseModel`, table `cases` — `Case` is a reserved word in PHP)
    EvidenceType.php
    EvidenceItem.php
    Hint.php
    RubricCriterion.php
    CaseAttempt.php
    InvestigationNote.php
    Diagnosis.php
    EvidenceView.php
    HintUnlock.php
    Evaluation.php
    EvaluationCriterionResult.php
  Policies/
    CasePolicy.php
    CaseAttemptPolicy.php
    EvidenceItemPolicy.php
  Repositories/
    Contracts/
      CaseRepositoryInterface.php
      EvidenceItemRepositoryInterface.php
      CaseAttemptRepositoryInterface.php
      DiagnosisRepositoryInterface.php
      EvaluationRepositoryInterface.php
      HintRepositoryInterface.php
    Eloquent/
      EloquentCaseRepository.php
      EloquentEvidenceItemRepository.php
      EloquentCaseAttemptRepository.php
      EloquentDiagnosisRepository.php
      EloquentEvaluationRepository.php
      EloquentHintRepository.php
  Services/
    CaseCatalogService.php
    CaseAttemptService.php          (start attempt, enforce reattempt policy)
    EvidenceInvestigationService.php (fetch + record evidence views)
    HintService.php                 (unlock + apply penalty)
    NoteService.php                 (autosave notes)
    DiagnosisService.php            (submit + orchestrate evaluation)
    EvaluationService.php           (resolve strategies, persist results)
    AnalyticsService.php            (instructor/admin aggregates)
  Evaluation/
    Contracts/
      EvaluationStrategyInterface.php
    Strategies/
      KeywordMatchStrategy.php
      EvidenceCitationStrategy.php
      ManualReviewStrategy.php       (stub: flags for instructor review, score pending)
    EvaluationStrategyResolver.php   (factory: matching_type → strategy instance)
  Providers/
    RepositoryServiceProvider.php    (binds interfaces → Eloquent implementations)
    EventServiceProvider.php

resources/
  views/
    layouts/ (app.blade.php, admin.blade.php)
    student/ (dashboard, catalog, case-details, investigation, evaluation)
    admin/   (dashboard, cases, evidence, rubrics, users, analytics)
    components/ (evidence viewers: log-viewer, code-viewer, db-snapshot-viewer, api-response-viewer, screenshot-viewer)
  js/
    evidence-viewers/ (light JS for tabbed evidence explorer, note autosave via fetch, hint confirm modal)

database/
  migrations/
  seeders/
    RoleSeeder.php, EvidenceTypeSeeder.php, CategorySeeder.php, DemoCaseSeeder.php
  factories/

routes/
  web.php     (student-facing + shared)
  admin.php   (grouped, prefixed /admin, role:admin middleware)
```

## 3. How Each Component Communicates — Concrete Trace

**Example 1: Student views an evidence item**

1. `GET /cases/{case}/attempts/{attempt}/evidence/{evidence}` → route middleware `auth`, `EnsureAttemptBelongsToUser`.
2. `EvidenceController@show` calls `CasePolicy::view` implicitly via route model binding + `authorize()`, then calls `EvidenceInvestigationService::recordView($attempt, $evidenceItem)`.
3. Service calls `EvidenceItemRepositoryInterface::find()` to fetch the item, then fires `EvidenceViewed` event (does **not** write the log itself — Single Responsibility: the service's job is "return the evidence to show," not "know how analytics are stored").
4. `RecordEvidenceView` listener (queued or sync) persists/increments the `evidence_views` row via `CaseAttemptRepositoryInterface`.
5. Controller returns a Blade view selecting the correct viewer component (`components.evidence.log-viewer`, `code-viewer`, etc.) based on `evidence_type.code` — a simple Blade `@switch`, not a business decision, so it stays in the view layer.

**Example 2: Student submits a diagnosis**

1. `POST /attempts/{attempt}/diagnosis` → `SubmitDiagnosisRequest` validates shape (root cause required, fix required, confidence in enum) and authorizes via `CaseAttemptPolicy::submit` (rejects if already submitted or attempt doesn't belong to user).
2. `DiagnosisController@store` calls `DiagnosisService::submit($attempt, $validated)`.
3. `DiagnosisService` persists via `DiagnosisRepositoryInterface`, marks attempt `submitted` via `CaseAttemptRepositoryInterface`, then calls `EvaluationService::evaluate($attempt, $diagnosis)`.
4. `EvaluationService` loads the case's `rubric_criteria`, and for each one asks `EvaluationStrategyResolver::resolve($criterion->matching_type)` for the right `EvaluationStrategyInterface` implementation (`KeywordMatchStrategy` for keyword criteria, `EvidenceCitationStrategy` for "did they reference the right evidence" criteria). Each strategy returns a `CriterionResult` value object (score, feedback).
5. `EvaluationService` sums results into an `Evaluation` + `EvaluationCriterionResult[]`, persists via `EvaluationRepositoryInterface`, marks attempt `completed`, fires `CaseAttemptCompleted`.
6. `CaseAttemptCompleted` listener updates denormalized dashboard stats (e.g. `UpdateStudentProgressStats`) — kept out of the critical path logic so `DiagnosisService`/`EvaluationService` don't need to know dashboards exist (Open/Closed: add a new listener for e.g. badges later without touching evaluation code).
7. Controller redirects to `EvaluationController@show`.

**Example 3: Admin publishes a case**

1. `Admin\CaseController@update` (route group `middleware(['auth','role:admin'])`) validates via `UpdateCaseRequest`, authorizes via `CasePolicy::update`.
2. Calls `CaseCatalogService::publish($case)` which checks invariants (at least one evidence item, at least one rubric criterion, weights sum > 0) before flipping `status` to `published` via `CaseRepositoryInterface` — business rule lives in the Service, not the controller or a database trigger.

## 4. Middleware Summary

| Middleware | Purpose |
|---|---|
| `auth` (Breeze default) | Require login |
| `EnsureUserHasRole:admin` / `:instructor` | Route-group gating for `/admin/*` |
| `EnsureAttemptBelongsToUser` | Prevents a student from opening someone else's `case_attempt` by guessing an ID |
| `EnsureAttemptNotAlreadySubmitted` | Blocks note/evidence/hint routes once an attempt is `submitted`/`completed` |

## 5. Policies Summary

| Policy | Key methods |
|---|---|
| `CasePolicy` | `view` (published, or author/admin sees drafts), `create`/`update`/`delete` (admin only) |
| `CaseAttemptPolicy` | `view`, `addNote`, `requestHint`, `submit` (attempt must belong to user and be `in_progress`) |
| `EvidenceItemPolicy` | `view` (case must be published, or admin) |

## 6. Why this satisfies SOLID (short version)

- **S**: Controllers do HTTP only; Services own one use case each; Strategies own one scoring algorithm each.
- **O**: New evidence type → new `evidence_types` row + new Blade component, zero changes to existing controllers/services. New evaluation method → new `EvaluationStrategyInterface` implementation, zero changes to `EvaluationService`.
- **L**: Any `EvaluationStrategyInterface` implementation is substitutable wherever the interface is type-hinted; same for repository implementations (swap Eloquent for a cache-backed one in tests).
- **I**: Repository interfaces are narrow and per-aggregate rather than one giant `RepositoryInterface` with 40 methods.
- **D**: Services depend on repository/strategy **interfaces**, bound in `RepositoryServiceProvider`; concrete Eloquent classes are an implementation detail.
