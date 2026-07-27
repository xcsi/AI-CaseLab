<?php

use App\Http\Controllers\Admin\CaseController as AdminCaseController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HintController;
use App\Http\Controllers\Admin\RubricCriterionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\CaseCatalogController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Models\CaseModel;
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

// Incident Briefing doesn't exist yet (a later screen) — kept as a real
// placeholder route, same scaffolding pattern as /work-history below, so
// the catalog's card links have somewhere real to go instead of a 404.
Route::get('/incidents/{case:slug}', function (CaseModel $case) {
    return view('placeholder', ['title' => 'Incident Briefing']);
})->name('cases.show');

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
