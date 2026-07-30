<?php

namespace App\Providers;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Infrastructure\Llm\LlmClientFactory;
use App\Discussion\Testing\FakeLlmClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

            return;
        }

        $this->app->singleton(
            LlmClientInterface::class,
            fn () => (new LlmClientFactory())->build(),
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Cost-abuse mitigation for the messages endpoint specifically
        // (docs/13-ai-discussion-engine-design.md §8) — "a per-user,
        // per-attempt rate limit," keyed by both so one student's usage on
        // one attempt never affects their limit on a different attempt (or
        // another student's, obviously). This is the second half of §8's
        // cost-abuse mitigation; max_rounds (already enforced in
        // DiscussionService) is the first.
        RateLimiter::for('discussion-messages', function (Request $request) {
            // The route parameter may or may not be resolved to a CaseAttempt
            // yet depending on middleware ordering (SubstituteBindings vs.
            // this route's throttle middleware) — handle both rather than
            // assuming one, since only the identifier is needed here, not
            // the model itself.
            $attempt = $request->route('attempt');
            $attemptId = is_object($attempt) ? $attempt->getKey() : $attempt;

            return Limit::perMinute(10)->by($request->user()?->id.':'.$attemptId);
        });
    }
}
