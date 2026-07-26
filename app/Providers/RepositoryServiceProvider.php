<?php

namespace App\Providers;

use App\Repositories\Contracts\CaseAttemptRepositoryInterface;
use App\Repositories\Contracts\CaseRepositoryInterface;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Repositories\Contracts\EvaluationRepositoryInterface;
use App\Repositories\Contracts\EvidenceItemRepositoryInterface;
use App\Repositories\Contracts\HintRepositoryInterface;
use App\Repositories\Eloquent\EloquentCaseAttemptRepository;
use App\Repositories\Eloquent\EloquentCaseRepository;
use App\Repositories\Eloquent\EloquentDiagnosisRepository;
use App\Repositories\Eloquent\EloquentEvaluationRepository;
use App\Repositories\Eloquent\EloquentEvidenceItemRepository;
use App\Repositories\Eloquent\EloquentHintRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CaseRepositoryInterface::class, EloquentCaseRepository::class);
        $this->app->bind(EvidenceItemRepositoryInterface::class, EloquentEvidenceItemRepository::class);
        $this->app->bind(CaseAttemptRepositoryInterface::class, EloquentCaseAttemptRepository::class);
        $this->app->bind(DiagnosisRepositoryInterface::class, EloquentDiagnosisRepository::class);
        $this->app->bind(EvaluationRepositoryInterface::class, EloquentEvaluationRepository::class);
        $this->app->bind(HintRepositoryInterface::class, EloquentHintRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
