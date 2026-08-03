<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Infrastructure\Llm\ChainedLlmClient;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionTurnRole;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Services\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves discussion_turns.fallback_log persistence for Phase 20 Milestone
 * 1, per docs/13-ai-discussion-engine-design.md §9.1: a FakeLlmClient
 * scripted to fail tier 1 and succeed tier 2 produces a correctly
 * populated log, verified through the full DiscussionService path — not
 * just ChainedLlmClient in isolation, which
 * tests/Feature/Discussion/ChainedLlmClientTest.php (Phase 14) already
 * covers at the class level.
 *
 * The container's testing-environment binding (DiscussionServiceProvider)
 * points LlmClientInterface directly at a single FakeLlmClient, not a
 * ChainedLlmClient — so this test rebinds the container to a real
 * ChainedLlmClient composed of two FakeLlmClient tiers, the only way to
 * actually exercise a fallback through DiscussionService rather than
 * around it.
 */
class DiscussionServiceFallbackLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_log_is_persisted_on_the_ai_turn_when_the_primary_tier_fails_and_the_next_tier_serves_it(): void
    {
        $tier1 = new FakeLlmClient();
        $tier1->willThrow(new LlmProviderUnavailableException('ollama unreachable'));

        $tier2 = new FakeLlmClient();
        $tier2->willReturn(new LlmTurnResult(
            'What in the evidence supports that?',
            DiscussionVerdict::Continue,
            provider: 'openrouter',
            model: 'some-free-model',
        ));

        $this->app->instance(LlmClientInterface::class, new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
            ['name' => 'openrouter_free', 'client' => $tier2],
        ]));

        $attempt = $this->attempt();

        $session = app(DiscussionService::class)->start($attempt, 'I think the gateway is down.', 'mentor');

        $aiTurn = $session->turns()->where('role', DiscussionTurnRole::Ai->value)->firstOrFail();

        $this->assertSame(
            [['tier' => 'ollama', 'result' => 'ollama unreachable']],
            $aiTurn->fallback_log
        );
        $this->assertSame('openrouter', $aiTurn->provider);
    }

    public function test_fallback_log_stays_null_when_the_primary_tier_serves_the_turn_directly(): void
    {
        $attempt = $this->attempt();

        app(LlmClientInterface::class)->willReturn(new LlmTurnResult(
            'What in the evidence supports that?',
            DiscussionVerdict::Continue,
            provider: 'ollama',
            model: 'qwen2.5:7b',
        ));

        $session = app(DiscussionService::class)->start($attempt, 'I think the gateway is down.', 'mentor');

        $aiTurn = $session->turns()->where('role', DiscussionTurnRole::Ai->value)->firstOrFail();

        $this->assertNull($aiTurn->fallback_log);
    }

    private function attempt(): CaseAttempt
    {
        $case = CaseModel::factory()->create([
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => 6,
        ]);

        return CaseAttempt::factory()->create(['case_id' => $case->id]);
    }
}
