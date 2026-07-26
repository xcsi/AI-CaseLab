<?php

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

// Placeholder routes for the admin shell — implemented in Phase 4 onward.
// Not yet role-gated (roles arrive in Phase 2); just needs to render so the
// admin navigation partial has real routes to link to.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('placeholder', ['title' => 'Admin Dashboard', 'layout' => 'admin']);
    })->name('dashboard');

    Route::get('/cases', function () {
        return view('placeholder', ['title' => 'Cases', 'layout' => 'admin']);
    })->name('cases.index');

    Route::get('/categories', function () {
        return view('placeholder', ['title' => 'Categories', 'layout' => 'admin']);
    })->name('categories.index');

    Route::get('/users', function () {
        return view('placeholder', ['title' => 'Users', 'layout' => 'admin']);
    })->name('users.index');

    Route::get('/analytics', function () {
        return view('placeholder', ['title' => 'Analytics', 'layout' => 'admin']);
    })->name('analytics.index');
});

require __DIR__.'/auth.php';
