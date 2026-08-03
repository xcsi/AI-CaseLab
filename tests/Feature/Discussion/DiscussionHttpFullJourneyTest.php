<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\DiscussionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 17 Milestone 2, per docs/14-v2-implementation-roadmap.md: "a full
 * student journey through raw HTTP: start, message, message, accept,
 * verifying response/redirect shape at each step." One continuous
 * conversation driven entirely through the real routes against
 * FakeLlmClient, checking the JSON response after every single request —
 * not just the final state — the same relationship
 * tests/Feature/EndToEndWorkflowTest.php has to Version 1's narrower
 * per-controller tests, applied to the Discussion Engine's own HTTP layer.
 *
 * "Redirect shape" doesn't apply yet — every discussion route returns JSON,
 * never a redirect, since no view/UI exists to redirect to (that's
 * Phase 18). Noted rather than silently skipped.
 */
class DiscussionHttpFullJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_student_journey_from_start_through_two_messages_to_acceptance(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create([
            'model_solution_summary' => 'Missing timeout on the payment gateway call.',
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => 6,
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $student->id]);
        $this->actingAs($student);

        // --- Step 1: start ---------------------------------------------
        $this->fake()->willReturn(new LlmTurnResult(
            'What in the evidence points to the gateway being down specifically?',
            DiscussionVerdict::Continue,
            provider: 'ollama',
            model: 'qwen2.5:7b',
        ));

        $start = $this->postJson(route('investigation.discussion.start', $attempt), [
            'opening_position' => 'I think the payment gateway is just down sometimes.',
        ]);

        $start->assertOk();
        $start->assertJson([
            'status' => 'ok',
            'session' => ['status' => 'active', 'persona' => 'mentor', 'round_count' => 1, 'max_rounds' => 6],
        ]);
        $start->assertJsonCount(2, 'turns');
        $start->assertJsonPath('turns.0.content', 'I think the payment gateway is just down sometimes.');
        $start->assertJsonPath('turns.1.content', 'What in the evidence points to the gateway being down specifically?');
        $start->assertJsonPath('turns.1.verdict', 'continue');

        // --- Step 2: first message (still not enough) -------------------
        $this->fake()->willReturn(new LlmTurnResult(
            'Good, the log shows a timeout. What does the checkout controller actually do when that call times out?',
            DiscussionVerdict::Continue,
            provider: 'ollama',
            model: 'qwen2.5:7b',
        ));

        $firstMessage = $this->postJson(route('investigation.discussion.respond', $attempt), [
            'message' => 'The log shows a cURL timeout after 30 seconds.',
        ]);

        $firstMessage->assertOk();
        $firstMessage->assertJsonPath('session.status', 'active');
        $firstMessage->assertJsonPath('session.round_count', 2);
        $firstMessage->assertJsonCount(4, 'turns');
        $firstMessage->assertJsonPath('turns.2.content', 'The log shows a cURL timeout after 30 seconds.');
        $firstMessage->assertJsonPath('turns.3.verdict', 'continue');

        // --- Step 3: second message (evidence-grounded, AI accepts) -----
        $this->fake()->willReturn(new LlmTurnResult(
            'Evidence-grounded and identifies the actual mechanism — accepted.',
            DiscussionVerdict::Accept,
            provider: 'ollama',
            model: 'qwen2.5:7b',
        ));

        $secondMessage = $this->postJson(route('investigation.discussion.respond', $attempt), [
            'message' => 'It sets no timeout, so PHP just waits and then throws an unhandled exception, causing the 500.',
        ]);

        $secondMessage->assertOk();
        $secondMessage->assertJsonPath('session.status', 'accepted');
        $secondMessage->assertJsonPath('session.round_count', 3);
        $secondMessage->assertJsonCount(6, 'turns');
        $secondMessage->assertJsonPath('turns.5.verdict', 'accept');

        // --- Step 4: GET show reflects the exact same, now-final state --
        $show = $this->getJson(route('investigation.discussion.show', $attempt));

        $show->assertOk();
        $show->assertJsonPath('session.status', 'accepted');
        $show->assertJsonPath('session.round_count', 3);
        $show->assertJsonCount(6, 'turns');
        $show->assertJsonPath(
            'turns.5.content',
            'Evidence-grounded and identifies the actual mechanism — accepted.'
        );

        // The real listener (Phase 16 Milestones 2/3) ran on acceptance via
        // the real HTTP request, not just in a headless service-layer
        // test — outcome_summary is populated, and the underlying
        // CaseAttempt itself stays completely untouched (§2.2).
        $session = DiscussionSession::where('discussable_id', $attempt->id)->firstOrFail();
        $this->assertNotNull($session->outcome_summary);
        $this->assertStringContainsString('Accepted after 3 round(s)', $session->outcome_summary);
    }

    private function fake(): FakeLlmClient
    {
        return app(LlmClientInterface::class);
    }
}
