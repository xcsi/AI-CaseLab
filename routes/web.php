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
use App\Models\CaseAttempt;
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

// Investigation Workspace — Milestone 1 (shell/layout only, see
// CaseAttemptController::show()). EnsureAttemptBelongsToUser protects
// every attempt-scoped route regardless of how much of the workspace is
// built yet.
Route::get('/investigation/{attempt}', [CaseAttemptController::class, 'show'])
    ->middleware(['auth', 'attempt.owner'])
    ->name('investigation.show');

// Performance Review is a later screen — kept as a real placeholder route
// (same scaffolding pattern used throughout this phase) so the Briefing's
// "View Past Report" link has somewhere real to go instead of a 404.
Route::get('/performance-review/{attempt}', function (CaseAttempt $attempt) {
    return view('placeholder', ['title' => 'Performance Review']);
})->middleware(['auth', 'attempt.owner'])->name('performance-review.show');

Route::get('/performance-review/{attempt}', function (CaseAttempt $attempt) {
    return view('placeholder', ['title' => 'Performance Review']);
})->middleware(['auth', 'attempt.owner'])->name('performance-review.show');

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
