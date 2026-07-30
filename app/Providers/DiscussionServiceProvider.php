<?php

namespace App\Providers;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Testing\FakeLlmClient;
use Illuminate\Support\ServiceProvider;

class DiscussionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            $this->app->singleton(LlmClientInterface::class, FakeLlmClient::class);
        }

        // Production binding — LlmClientFactory resolving to the cost-safe
        // ordered fallback chain (docs/13-ai-discussion-engine-design.md
        // §1.4) — arrives in Phase 14 Milestone 5.
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
