<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\LeakedReplyException;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
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
 * Proves the HTTP entry layer for Phase 17 Milestone 1, per
 * docs/13-ai-discussion-engine-design.md §10 — routes, DiscussionController,
 * Form Requests, and the existing attempt.owner/auth middleware, entirely
 * against FakeLlmClient. No UI assertions (nothing renders a view — every
 * response here is JSON), per this milestone's scope.
 */
class DiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_from_every_discussion_route(): void
    {
        $attempt = $this->attempt();

        $this->post(route('investigation.discussion.start', $attempt))->assertRedirect(route('login'));
        $this->get(route('investigation.discussion.show', $attempt))->assertRedirect(route('login'));
        $this->post(route('investigation.discussion.respond', $attempt))->assertRedirect(route('login'));
        $this->post(route('investigation.discussion.end', $attempt))->assertRedirect(route('login'));
    }

    public function test_a_different_student_cannot_reach_someone_elses_attempt_discussion(): void
    {
        $attempt = $this->attempt();
        $otherStudent = User::factory()->create();

        $this->actingAs($otherStudent)
            ->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'Guess.'])
            ->assertForbidden();
    }

    public function test_start_creates_a_session_and_returns_it_as_json(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('What supports that?'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'I think the gateway is down.', 'persona' => 'mentor']
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('session.status', 'active');
        $response->assertJsonPath('session.persona', 'mentor');
        $response->assertJsonPath('session.round_count', 1);
        $response->assertJsonCount(2, 'turns');
        $response->assertJsonPath('turns.0.role', 'student');
        $response->assertJsonPath('turns.1.role', 'ai');
        $response->assertJsonPath('turns.1.content', 'What supports that?');

        $this->assertSame(1, DiscussionSession::count());
    }

    public function test_start_falls_back_to_the_cases_default_persona_when_none_is_given(): void
    {
        [$student, $attempt] = $this->ownedAttempt(defaultPersona: 'interviewer');
        $this->fake()->willReturn($this->continue('Convince me.'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'My theory.']
        );

        $response->assertOk();
        $response->assertJsonPath('session.persona', 'interviewer');
    }

    public function test_start_requires_an_opening_position(): void
    {
        [$student, $attempt] = $this->ownedAttempt();

        $this->actingAs($student)
            ->postJson(route('investigation.discussion.start', $attempt), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_position']);
    }

    public function test_start_rejects_an_unrecognized_persona(): void
    {
        [$student, $attempt] = $this->ownedAttempt();

        $this->actingAs($student)
            ->postJson(route('investigation.discussion.start', $attempt), [
                'opening_position' => 'My theory.',
                'persona' => 'not-a-real-persona',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['persona']);
    }

    public function test_start_returns_a_conflict_when_a_session_is_already_active(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('Challenge.'));
        $this->actingAs($student)->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'First.']);

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Second attempt.']
        );

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'error');
    }

    public function test_respond_appends_a_round_and_returns_the_updated_session(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('Challenge one.'));
        $this->actingAs($student)->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'Opening.']);

        $this->fake()->willReturn($this->accept('Accepted — well reasoned.'));
        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.respond', $attempt),
            ['message' => 'It is X, per the log.']
        );

        $response->assertOk();
        $response->assertJsonPath('session.status', 'accepted');
        $response->assertJsonCount(4, 'turns');
    }

    public function test_respond_requires_a_message(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('Challenge.'));
        $this->actingAs($student)->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'Opening.']);

        $this->actingAs($student)
            ->postJson(route('investigation.discussion.respond', $attempt), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message']);
    }

    public function test_respond_returns_not_found_when_no_session_has_ever_been_started(): void
    {
        [$student, $attempt] = $this->ownedAttempt();

        $this->actingAs($student)
            ->postJson(route('investigation.discussion.respond', $attempt), ['message' => 'Hello?'])
            ->assertNotFound();
    }

    public function test_respond_returns_a_conflict_once_the_session_is_already_terminal(): void
    {
        [$student, $attempt] = $this->ownedAttempt(maxRounds: 1);
        $this->fake()->willReturn($this->continue('Challenge.'));
        // max_rounds = 1 -> already MaxRoundsReached after start()
        $this->actingAs($student)->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'Opening.']);

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.respond', $attempt),
            ['message' => 'One more try.']
        );

        $response->assertStatus(409);
    }

    public function test_end_transitions_the_session_and_returns_it(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('Challenge.'));
        $this->actingAs($student)->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'Opening.']);

        $response = $this->actingAs($student)->postJson(route('investigation.discussion.end', $attempt));

        $response->assertOk();
        $response->assertJsonPath('session.status', 'ended_by_student');
    }

    public function test_show_returns_not_found_before_any_discussion_has_started(): void
    {
        [$student, $attempt] = $this->ownedAttempt();

        $this->actingAs($student)
            ->getJson(route('investigation.discussion.show', $attempt))
            ->assertNotFound();
    }

    public function test_show_returns_the_transcript_once_a_discussion_exists(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willReturn($this->continue('Challenge.'));
        $this->actingAs($student)->postJson(route('investigation.discussion.start', $attempt), ['opening_position' => 'Opening.']);

        $response = $this->actingAs($student)->getJson(route('investigation.discussion.show', $attempt));

        $response->assertOk();
        $response->assertJsonCount(2, 'turns');
    }

    public function test_a_chain_exhaustion_returns_a_typed_unavailable_response_naming_nothing_provider_specific(): void
    {
        [$student, $attempt] = $this->ownedAttempt();
        $this->fake()->willThrow(new NoLlmProviderAvailableException('all tiers exhausted'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Opening.']
        );

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'unavailable');
        $this->assertStringNotContainsString('provider', strtolower($response->json('message')));
        $this->assertStringNotContainsString('ollama', strtolower($response->json('message')));
    }

    public function test_a_leaked_reply_returns_the_same_generic_unavailable_response_not_a_500(): void
    {
        $modelSolution = 'The gateway call has no timeout configured at all.';
        [$student, $attempt] = $this->ownedAttempt(modelSolution: $modelSolution);
        $this->fake()->willReturn($this->continue('Well, '.$modelSolution.' — does that match?'));

        $response = $this->actingAs($student)->postJson(
            route('investigation.discussion.start', $attempt),
            ['opening_position' => 'Opening.']
        );

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'unavailable');
    }

    private function attempt(): CaseAttempt
    {
        return $this->ownedAttempt()[1];
    }

    /**
     * @return array{0: User, 1: CaseAttempt}
     */
    private function ownedAttempt(?int $maxRounds = 6, ?string $defaultPersona = 'mentor', ?string $modelSolution = 'The root cause is X.'): array
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create([
            'model_solution_summary' => $modelSolution,
            'discussion_enabled' => true,
            'discussion_default_persona' => $defaultPersona,
            'discussion_max_rounds' => $maxRounds,
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $student->id]);

        return [$student, $attempt];
    }

    private function continue(string $replyText): LlmTurnResult
    {
        return new LlmTurnResult($replyText, DiscussionVerdict::Continue, provider: 'ollama', model: 'qwen2.5:7b');
    }

    private function accept(string $replyText): LlmTurnResult
    {
        return new LlmTurnResult($replyText, DiscussionVerdict::Accept, provider: 'ollama', model: 'qwen2.5:7b');
    }

    private function fake(): FakeLlmClient
    {
        return app(LlmClientInterface::class);
    }
}
