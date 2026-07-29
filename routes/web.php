<?php

use App\Http\Controllers\Admin\CaseController as AdminCaseController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HintController;
use App\Http\Controllers\Admin\RubricCriterionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\CaseAttemptController;
use App\Http\Controllers\Student\CaseCatalogController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\DiagnosisController;
use App\Http\Controllers\Student\EvidenceController;
use App\Http\Controllers\Student\HintController as StudentHintController;
use App\Http\Controllers\Student\NotebookController;
use App\Http\Controllers\Student\PerformanceReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The Inbox — kept at /dashboard per the approved UX spec's decision log
// (avoids overriding Breeze's password-reset/verification redirect
// convention for a cosmetic URL change); the on-page heading reads "Inbox".
Route::get('/dashboard', [StudentDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Assigned Incidents — guest-accessible (the Shell's "View a Sample
// Incident" guest link points here), matching the catalog's role as a
// public-ish backlog before a student commits to an incident.
Route::get('/incidents', [CaseCatalogController::class, 'index'])->name('cases.index');

// Incident Briefing — withTrashed() so an archived/unpublished case still
// resolves to the controller (which renders the friendly "no longer
// available" state) instead of a raw 404, per the approved UX spec.
Route::get('/incidents/{case:slug}', [CaseCatalogController::class, 'show'])
    ->withTrashed()
    ->name('cases.show');

// Starts or resumes the student's attempt at this case — real behavior
// (CaseAttemptService::start(), Phase 5 scope per the architecture doc),
// redirecting into Investigation Workspace, which doesn't exist yet.
Route::post('/incidents/{case:slug}/start', [CaseAttemptController::class, 'store'])
    ->middleware('auth')
    ->name('attempts.store');

// Investigation Workspace — Milestone 2 (Evidence Explorer + Viewer; see
// CaseAttemptController::show()). EnsureAttemptBelongsToUser protects
// every attempt-scoped route regardless of how much of the workspace is
// built yet.
Route::get('/investigation/{attempt}', [CaseAttemptController::class, 'show'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.show');

// Records that the student opened this evidence item (EvidenceInvestigationService
// -> EvidenceViewed event -> RecordEvidenceView listener, per the architecture
// doc's Example 1 trace). Fired client-side whenever a tab is activated.
Route::post('/investigation/{attempt}/evidence/{evidenceItem}/view', [EvidenceController::class, 'recordView'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.evidence.view');

// Engineering Notebook autosave (Milestone 3) — debounced PATCH from the
// workspace's notebook textarea, upserting the attempt's single
// investigation_notes row.
Route::patch('/investigation/{attempt}/notes', [NotebookController::class, 'update'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.notes.update');

// Hint unlocking (Milestone 4) — idempotent penalty deduction via
// HintUnlockService. abort_unless inside the controller guards against a
// hint from a different case being unlocked against this attempt.
Route::post('/investigation/{attempt}/hints/{hint}/unlock', [StudentHintController::class, 'unlock'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.hints.unlock');

// Submit Diagnosis (Milestone 6) — a deliberate separate step from the
// Workspace, not another workspace tab, per the approved UX spec. Redirects
// to the Performance Review placeholder on success; DiagnosisSubmissionService
// is idempotent so a resubmission never creates a second diagnosis.
Route::get('/investigation/{attempt}/report', [DiagnosisController::class, 'create'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.diagnosis.create');

Route::post('/investigation/{attempt}/report', [DiagnosisController::class, 'store'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.diagnosis.store');

// Performance Review (Milestone 7) — the final screen in the investigation
// journey. Redirects back to the Workspace if the attempt hasn't been
// submitted yet; renders an "awaiting evaluation" state instead of a score
// when no Evaluation row exists (the Evaluation Engine is Phase 10, not
// built yet) rather than fabricating one.
Route::get('/performance-review/{attempt}', [PerformanceReviewController::class, 'show'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('performance-review.show');

Route::get('/work-history', function () {
    return view('placeholder', ['title' => 'Work History']);
})->middleware(['auth'])->name('progress.index');

// Admin shell — gated to admin and instructor (read-only for instructors,
// per the SRS's content-management split); Policies enforce the finer-grained
// write restrictions per resource.
Route::middleware(['auth', 'role:admin,instructor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('cases', AdminCaseController::class)->except(['show']);
    Route::post('cases/{case}/publish', [AdminCaseController::class, 'publish'])->name('cases.publish');

    Route::resource('cases.hints', HintController::class)->shallow()->only(['store', 'update', 'destroy']);
    Route::post('hints/{hint}/move-up', [HintController::class, 'moveUp'])->name('hints.move-up');
    Route::post('hints/{hint}/move-down', [HintController::class, 'moveDown'])->name('hints.move-down');

    Route::resource('cases.rubric-criteria', RubricCriterionController::class)->shallow()->only(['store', 'update', 'destroy']);

    Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);

    Route::get('/users', function () {
        return view('placeholder', ['title' => 'Users', 'layout' => 'admin']);
    })->name('users.index');

    Route::get('/analytics', function () {
        return view('placeholder', ['title' => 'Analytics', 'layout' => 'admin']);
    })->name('analytics.index');
});

require __DIR__.'/auth.php';
