<?php

use App\Http\Controllers\Admin\CaseController as AdminCaseController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HintController;
use App\Http\Controllers\Admin\RubricCriterionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Placeholder routes for the student shell — implemented in later phases
// (Phase 5 catalog, Phase 11 progress/analytics). Kept as real named routes
// so the navigation partial can call route() without erroring.
Route::get('/cases', function () {
    return view('placeholder', ['title' => 'Case Catalog']);
})->name('cases.index');

Route::get('/progress', function () {
    return view('placeholder', ['title' => 'My Progress']);
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
