<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the messages endpoint's rate limit for Phase 17 Milestone 4, per
 * docs/13-ai-discussion-engine-design.md §8's cost-abuse mitigation: "a
 * per-user, per-attempt rate limit." Entirely against FakeLlmClient — the
 * throttle middleware rejects requests before DiscussionService or
 * LlmClientInterface is ever touched, so no network boundary is crossed
 * here either.
 */
class DiscussionMessagesRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_eleventh_message_within_a_minute_is_rejected(): void
    {
        [$student, $attempt] = $this->startedDiscussion();

        for ($i = 1; $i <= 10; $i++) {
            $this->fake()->willReturn(new LlmTurnResult("Reply {$i}.", DiscussionVerdict::Continue));

            $this->actingAs($student)
                ->postJson(route('investigation.discussion.respond', $attempt), ['message' => "Message {$i}."])
                ->assertOk();
        }

        // The 11th request within the same window never reaches the
        // controller/service at all — no scripted response is queued for
        // it, so if the throttle didn't block it, FakeLlmClient's own
        // "queue exhausted" error would surface instead of a 429.
        $this->actingAs($student)
            ->postJson(route('investigation.discussion.respond', $attempt), ['message' => 'Message 11.'])
            ->assertStatus(429);
    }

    public function test_the_limit_is_scoped_per_attempt_not_shared_across_a_students_other_discussions(): void
    {
        $student = User::factory()->create();
        [, $attemptA] = $this->startedDiscussion($student);
        [, $attemptB] = $this->startedDiscussion($student);

        for ($i = 1; $i <= 10; $i++) {
            $this->fake()->willReturn(new LlmTurnResult("A reply {$i}.", DiscussionVerdict::Continue));
            $this->actingAs($student)
                ->postJson(route('investigation.discussion.respond', $attemptA), ['message' => "Message {$i}."])
                ->assertOk();
        }

        // Attempt A's budget is now exhausted...
        $this->actingAs($student)
            ->postJson(route('investigation.discussion.respond', $attemptA), ['message' => 'One more.'])
            ->assertStatus(429);

        // ...but attempt B, the same student's other discussion, has its
        // own independent budget and is completely unaffected.
        $this->fake()->willReturn(new LlmTurnResult('B reply.', DiscussionVerdict::Continue));
        $this->actingAs($student)
            ->postJson(route('investigation.discussion.respond', $attemptB), ['message' => 'First message here.'])
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: CaseAttempt}
     */
    private function startedDiscussion(?User $student = null): array
    {
        $student ??= User::factory()->create();
        $case = CaseModel::factory()->create([
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            // High enough that 10+ rounds in a test never hits
            // MaxRoundsReached and confuses a rate-limit-specific test.
            'discussion_max_rounds' => 100,
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $student->id]);

        $this->fake()->willReturn(new LlmTurnResult('Opening challenge.', DiscussionVerdict::Continue));
        $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Opening position.']
        )->assertOk();

        return [$student, $attempt];
    }

    private function fake(): FakeLlmClient
    {
        return app(LlmClientInterface::class);
    }
}
