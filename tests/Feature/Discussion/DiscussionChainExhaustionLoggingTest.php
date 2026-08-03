<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Proves structured application logging on full LLM fallback chain
 * exhaustion for Phase 20 Milestone 2, per
 * docs/13-ai-discussion-engine-design.md §1.4.5 — operational visibility
 * only, never surfaced to the student (DiscussionController's
 * unavailable() response, already covered by
 * tests/Feature/Discussion/DiscussionControllerTest.php, is unchanged).
 */
class DiscussionChainExhaustionLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_a_structured_warning_when_starting_a_discussion_hits_chain_exhaustion(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Engineering Discussion: LLM fallback chain exhausted.'
                    && $context['reason'] === 'all tiers exhausted'
                    && array_key_exists('attempt_id', $context)
                    && array_key_exists('case_id', $context);
            });

        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willThrow(new NoLlmProviderAvailableException('all tiers exhausted'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Opening.']
        );

        $response->assertStatus(503);
    }

    public function test_it_logs_a_structured_warning_including_the_session_when_responding_hits_chain_exhaustion(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('First challenge.'));
        $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Opening.']
        )->assertOk();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Engineering Discussion: LLM fallback chain exhausted.'
                    && $context['reason'] === 'all tiers exhausted, again'
                    && $context['persona'] === 'mentor'
                    && array_key_exists('discussion_session_id', $context);
            });

        $this->fake()->willThrow(new NoLlmProviderAvailableException('all tiers exhausted, again'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.respond', $attempt),
            ['message' => 'My response.']
        );

        $response->assertStatus(503);
    }

    public function test_it_does_not_log_anything_for_a_leaked_reply(): void
    {
        Log::shouldReceive('warning')->never();

        $modelSolution = 'The gateway call has no timeout configured at all.';
        [$student, $attempt] = $this->ownedAttempt(modelSolution: $modelSolution);
        $this->fake()->willReturn($this->continue('Well, '.$modelSolution.' — does that match?'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Opening.']
        );

        $response->assertStatus(503);
        $this->assertTrue(true, 'LeakedReplyException path reached without triggering Log::warning.');
    }

    /**
     * @return array{0: User, 1: CaseAttempt}
     */
    private function ownedAttempt(?string $modelSolution = 'The root cause is X.'): array
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create([
            'model_solution_summary' => $modelSolution,
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => 6,
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $student->id]);

        return [$student, $attempt];
    }

    private function continue(string $replyText): LlmTurnResult
    {
        return new LlmTurnResult($replyText, DiscussionVerdict::Continue, provider: 'ollama', model: 'qwen2.5:7b');
    }

    private function fake(): FakeLlmClient
    {
        return app(LlmClientInterface::class);
    }
}
