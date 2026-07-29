<?php

namespace App\Providers;

use App\Models\CaseModel;
use App\Policies\CasePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's policy auto-discovery guesses `CaseModelPolicy` from the
        // model's class name (`CaseModel`, renamed from `Case` — a reserved
        // word — per the Architecture doc). Bind explicitly so the policy
        // can keep the intended `CasePolicy` name.
        Gate::policy(CaseModel::class, CasePolicy::class);
    }
}
