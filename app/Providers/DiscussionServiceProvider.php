<?php

namespace App\Providers;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Infrastructure\Llm\LlmClientFactory;
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
        //
    }
}
