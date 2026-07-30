<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\DiscussionAlreadyActiveException;
use App\Discussion\Exceptions\DiscussionNotActiveException;
use App\Discussion\Exceptions\LeakedReplyException;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\DiscussionSession;
use App\Services\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Proves DiscussionService's state machine for Phase 16 Milestone 1, per
 * docs/13-ai-discussion-engine-design.md §2 — entirely against
 * FakeLlmClient, zero real network calls, zero HTTP layer (that's Phase 17).
 */
class DiscussionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_an_active_session_with_the_opening_turn_and_the_ai_first_challenge(): void
    {
        $attempt = $this->attempt(maxRounds: 6, modelSolution: 'Missing timeout on the gateway.');
        $this->fake()->willReturn($this->continueResult('What in the evidence supports that?'));

        $session = $this->service()->start($attempt, 'I think the gateway is down.', 'mentor');

        $this->assertSame(DiscussionStatus::Active, $session->status);
        $this->assertSame('mentor', $session->persona);
        $this->assertSame(1, $session->round_count);
        $this->assertSame(6, $session->max_rounds);
        $this->assertCount(2, $session->turns);

        $turns = $session->turns()->orderBy('sequence_order')->get();
        $this->assertSame(DiscussionTurnRole::Student, $turns[0]->role);
        $this->assertSame('I think the gateway is down.', $turns[0]->content);
        $this->assertSame(DiscussionTurnRole::Ai, $turns[1]->role);
        $this->assertSame('What in the evidence supports that?', $turns[1]->content);
        $this->assertSame(DiscussionVerdict::Continue, $turns[1]->verdict);
    }

    public function test_start_falls_back_to_the_cases_default_persona_when_none_is_given(): void
    {
        $attempt = $this->attempt(defaultPersona: 'interviewer');
        $this->fake()->willReturn($this->continueResult('Convince me.'));

        $session = $this->service()->start($attempt, 'My theory is X.');

        $this->assertSame('interviewer', $session->persona);
    }

    public function test_start_throws_when_no_persona_is_given_and_the_case_has_no_default(): void
    {
        $attempt = $this->attempt(defaultPersona: null);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->start($attempt, 'My theory is X.');
    }

    public function test_start_throws_when_a_session_is_already_active_for_the_attempt(): void
    {
        $attempt = $this->attempt();
        $this->fake()->willReturn($this->continueResult('First challenge.'));
        $this->service()->start($attempt, 'Opening position.', 'mentor');

        $this->expectException(DiscussionAlreadyActiveException::class);

        $this->service()->start($attempt, 'Trying again.', 'mentor');
    }

    public function test_respond_appends_a_round_and_stays_active_on_a_continue_verdict(): void
    {
        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willReturn($this->continueResult('Challenge one.'));
        $session = $this->service()->start($attempt, 'Opening.', 'mentor');

        $this->fake()->willReturn($this->continueResult('Challenge two.'));
        $session = $this->service()->respond($session, 'My reply to challenge one.');

        $this->assertSame(DiscussionStatus::Active, $session->status);
        $this->assertSame(2, $session->round_count);
        $this->assertCount(4, $session->turns);
    }

    public function test_respond_transitions_to_accepted_on_an_accept_verdict(): void
    {
        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willReturn($this->continueResult('Challenge.'));
        $session = $this->service()->start($attempt, 'Opening.', 'mentor');

        $this->fake()->willReturn($this->acceptResult('Nicely reasoned — accepted.'));
        $session = $this->service()->respond($session, 'It is a missing timeout, per the log.');

        $this->assertSame(DiscussionStatus::Accepted, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_respond_transitions_to_max_rounds_reached_once_the_cap_is_hit(): void
    {
        // max_rounds = 1: the AI's response to the OPENING position (round
        // 1) already puts round_count at the cap.
        $attempt = $this->attempt(maxRounds: 1);
        $this->fake()->willReturn($this->continueResult('Still not convinced.'));

        $session = $this->service()->start($attempt, 'Opening.', 'mentor');

        $this->assertSame(DiscussionStatus::MaxRoundsReached, $session->status);
        $this->assertSame(1, $session->round_count);
        $this->assertNotNull($session->ended_at);
    }

    public function test_an_end_unresolved_verdict_also_transitions_to_max_rounds_reached(): void
    {
        // No separate terminal status exists for "AI gave up early" — this
        // is a deliberate interpretation, documented in DiscussionService's
        // own docblock, not a silent assumption.
        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willReturn(new LlmTurnResult('This isn\'t converging.', DiscussionVerdict::EndUnresolved));

        $session = $this->service()->start($attempt, 'Opening.', 'mentor');

        $this->assertSame(DiscussionStatus::MaxRoundsReached, $session->status);
        $this->assertSame(1, $session->round_count); // well below the max_rounds cap of 6
    }

    public function test_respond_throws_when_the_session_is_already_terminal(): void
    {
        $attempt = $this->attempt(maxRounds: 1);
        $this->fake()->willReturn($this->continueResult('Challenge.'));
        $session = $this->service()->start($attempt, 'Opening.', 'mentor'); // immediately MaxRoundsReached

        $this->expectException(DiscussionNotActiveException::class);

        $this->service()->respond($session, 'One more try.');
    }

    public function test_end_transitions_an_active_session_to_ended_by_student(): void
    {
        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willReturn($this->continueResult('Challenge.'));
        $session = $this->service()->start($attempt, 'Opening.', 'mentor');

        $session = $this->service()->end($session);

        $this->assertSame(DiscussionStatus::EndedByStudent, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_end_throws_when_the_session_is_already_terminal(): void
    {
        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willReturn($this->acceptResult('Accepted.'));
        $session = $this->service()->start($attempt, 'Opening.', 'mentor');

        $this->expectException(DiscussionNotActiveException::class);

        $this->service()->end($session);
    }

    public function test_a_leaking_reply_is_never_persisted_and_the_students_own_turn_is_rolled_back_too(): void
    {
        $attempt = $this->attempt(modelSolution: 'The gateway call has no timeout configured at all.');
        $this->fake()->willReturn($this->continueResult(
            'Well, the gateway call has no timeout configured at all — does that match?'
        ));

        try {
            $this->service()->start($attempt, 'Opening position.', 'mentor');
            $this->fail('Expected LeakedReplyException was not thrown.');
        } catch (LeakedReplyException) {
            // expected
        }

        // Nothing was left behind — not the AI's leaking reply, and not
        // even the student's own opening turn, since the whole operation
        // ran inside one transaction that rolled back entirely.
        $this->assertSame(0, DiscussionSession::count());
        $this->assertDatabaseCount('discussion_turns', 0);
    }

    public function test_conversation_history_sent_to_the_llm_accumulates_correctly_across_turns(): void
    {
        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willReturn($this->continueResult('First challenge.'));
        $session = $this->service()->start($attempt, 'Opening position.', 'mentor');

        $this->fake()->willReturn($this->continueResult('Second challenge.'));
        $this->service()->respond($session, 'My response to the first challenge.');

        $secondCall = $this->fake()->recordedCalls()[1];

        $this->assertSame(
            [
                ['role' => 'student', 'content' => 'Opening position.'],
                ['role' => 'ai', 'content' => 'First challenge.'],
            ],
            $secondCall['conversationHistory']
        );
        $this->assertSame('My response to the first challenge.', $secondCall['newMessage']);
    }

    private function attempt(?int $maxRounds = 6, ?string $defaultPersona = 'mentor', ?string $modelSolution = 'The root cause is X.'): CaseAttempt
    {
        $case = CaseModel::factory()->create([
            'model_solution_summary' => $modelSolution,
            'discussion_enabled' => true,
            'discussion_default_persona' => $defaultPersona,
            'discussion_max_rounds' => $maxRounds,
        ]);

        return CaseAttempt::factory()->create(['case_id' => $case->id]);
    }

    private function continueResult(string $replyText): LlmTurnResult
    {
        return new LlmTurnResult($replyText, DiscussionVerdict::Continue, provider: 'ollama', model: 'qwen2.5:7b');
    }

    private function acceptResult(string $replyText): LlmTurnResult
    {
        return new LlmTurnResult($replyText, DiscussionVerdict::Accept, provider: 'ollama', model: 'qwen2.5:7b');
    }

    private function fake(): FakeLlmClient
    {
        return app(LlmClientInterface::class);
    }

    private function service(): DiscussionService
    {
        return app(DiscussionService::class);
    }
}
