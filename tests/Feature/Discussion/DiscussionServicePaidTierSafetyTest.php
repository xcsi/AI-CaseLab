<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
use App\Discussion\Infrastructure\Llm\LlmClientFactory;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Services\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The regression test Phase 20 Milestone 3 exists to add, per
 * docs/13-ai-discussion-engine-design.md §1.4.4: a paid-tier client is
 * never reached when LLM_ALLOW_PAID_FALLBACK=false — proven through the
 * full request path (DiscussionService -> a *real* LlmClientFactory-built
 * ChainedLlmClient -> real provider clients -> Http::fake()'s recorded
 * requests), not just LlmClientFactory::build()'s tier-name output in
 * isolation (tests/Feature/Discussion/LlmClientFactoryTest.php, Phase 14
 * Milestone 5, which this test extends rather than replaces).
 *
 * FakeLlmClient is deliberately not used here — the whole point is
 * exercising the real provider clients' HTTP calls (intercepted by
 * Http::fake(), so still zero real network calls) to prove the guarantee
 * at the actual wire boundary, the strongest place it could be checked.
 */
class DiscussionServicePaidTierSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paid_provider_is_never_called_through_the_full_request_path_when_paid_fallback_is_disabled(): void
    {
        // Every free/local tier configured and about to fail, plus a paid
        // tier left configured with a real-looking key but NOT enabled —
        // the exact "owner left keys sitting in config" scenario
        // LlmClientFactoryTest's docblock calls out as the critical case.
        config([
            'llm.ollama.base_url' => 'http://localhost:11434',
            'llm.openrouter.api_key' => 'or-key',
            'llm.gemini.api_key' => 'gemini-key',
            'llm.paid_fallback.allowed' => false,
            'llm.paid_fallback.provider' => 'openai',
            'llm.openai.api_key' => 'sk-live-key-that-must-never-be-used',
            'llm.anthropic.api_key' => 'sk-ant-live-key-that-must-never-be-used',
        ]);

        // Every configured tier fails identically (a 500), so the chain is
        // guaranteed to exhaust — the scenario where a bug would actually
        // fall through to a paid tier if the safety guarantee didn't hold.
        Http::fake(['*' => Http::response('', 500)]);

        $this->app->instance(LlmClientInterface::class, (new LlmClientFactory())->build());

        $case = CaseModel::factory()->create([
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => 6,
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);

        try {
            app(DiscussionService::class)->start($attempt, 'Opening position.', 'mentor');
            $this->fail('Expected NoLlmProviderAvailableException was not thrown.');
        } catch (NoLlmProviderAvailableException $e) {
            $this->assertStringContainsString('ollama', $e->getMessage());
            $this->assertStringContainsString('openrouter', $e->getMessage());
            $this->assertStringContainsString('gemini', $e->getMessage());
        }

        // Exactly the three free/local tiers were ever touched — proven at
        // the HTTP layer itself, not inferred from chain composition.
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'localhost:11434'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
    }
}
