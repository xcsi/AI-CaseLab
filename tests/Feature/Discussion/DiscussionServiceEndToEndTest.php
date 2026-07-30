<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\LeakedReplyException;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\AttemptStatus;
use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Enums\DiscussionVerdict;
use App\Events\DiscussionAccepted;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\DiscussionSession;
use App\Services\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * End-to-end coverage of every discussion flow DiscussionService supports,
 * purely through the service layer against FakeLlmClient — no HTTP, no
 * routes, no UI, no live LLM call — completing
 * docs/14-v2-implementation-roadmap.md's Phase 16 Milestone 5. Complements
 * rather than replaces DiscussionServiceTest/DiscussionAcceptedEventTest's
 * narrower per-behavior tests, the same relationship
 * tests/Feature/EndToEndWorkflowTest.php already has with Version 1's
 * per-controller tests: each test here walks one complete, realistic
 * scenario start to finish and checks state, persisted data, events, and
 * outcome_summary together, not one behavior in isolation.
 */
class DiscussionServiceEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_accepted__a_multi_round_discussion_that_converges_to_acceptance(): void
    {
        Event::fake([DiscussionAccepted::class]);

        $attempt = $this->attempt(maxRounds: 6, modelSolution: 'Missing timeout on the gateway call.');
        $service = $this->service();

        $this->fake()->willReturn($this->continue('What in the evidence supports that?'));
        $session = $service->start($attempt, 'I think the gateway is just down sometimes.', 'mentor');

        $this->fake()->willReturn($this->continue('Good, you cited the log. What does the code do about it?'));
        $session = $service->respond($session, 'The log shows a timeout error.');

        $this->fake()->willReturn($this->accept('Evidence-grounded and correctly identifies the mechanism — accepted.'));
        $session = $service->respond($session, 'The code sets no timeout, so it hangs until an unhandled exception causes the 500.');

        // State
        $this->assertSame(DiscussionStatus::Accepted, $session->status);
        $this->assertSame(3, $session->round_count);
        $this->assertNotNull($session->ended_at);

        // Persisted data — every turn, in order, with correct roles/content
        $turns = $session->turns()->orderBy('sequence_order')->get();
        $this->assertCount(6, $turns);
        $this->assertSame(
            [DiscussionTurnRole::Student, DiscussionTurnRole::Ai, DiscussionTurnRole::Student, DiscussionTurnRole::Ai, DiscussionTurnRole::Student, DiscussionTurnRole::Ai],
            $turns->pluck('role')->all()
        );
        $this->assertSame(DiscussionVerdict::Accept, $turns->last()->verdict);
        $this->assertSame('ollama', $turns->last()->provider);

        // Emitted event
        Event::assertDispatched(DiscussionAccepted::class, fn (DiscussionAccepted $e) => $e->session->id === $session->id);
    }

    public function test_flow_accepted__outcome_summary_is_populated_by_the_real_listener(): void
    {
        $attempt = $this->attempt(maxRounds: 6);
        $service = $this->service();

        $this->fake()->willReturn($this->continue('Challenge.'));
        $session = $service->start($attempt, 'Opening.', 'mentor');

        $this->fake()->willReturn($this->accept('Accepted.'));
        $session = $service->respond($session, 'It is X, confirmed by the log.');

        $session->refresh();
        $this->assertStringContainsString('Accepted after 2 round(s)', $session->outcome_summary);
        $this->assertStringContainsString('It is X, confirmed by the log.', $session->outcome_summary);
    }

    public function test_flow_ended_by_student__student_stops_the_discussion_before_any_resolution(): void
    {
        Event::fake([DiscussionAccepted::class]);

        $attempt = $this->attempt(maxRounds: 6);
        $service = $this->service();

        $this->fake()->willReturn($this->continue('Challenge one.'));
        $session = $service->start($attempt, 'Opening.', 'mentor');

        $this->fake()->willReturn($this->continue('Challenge two.'));
        $session = $service->respond($session, 'Reply one.');

        $session = $service->end($session);

        $this->assertSame(DiscussionStatus::EndedByStudent, $session->status);
        $this->assertSame(2, $session->round_count);
        $this->assertNotNull($session->ended_at);
        $this->assertCount(4, $session->turns);

        // Never accepted -> the accept-only side effects never fire
        Event::assertNotDispatched(DiscussionAccepted::class);
        $this->assertNull($session->fresh()->outcome_summary);

        // The underlying CaseAttempt is completely unaffected by an
        // unresolved discussion — this never blocks diagnosis submission,
        // per §2.2.
        $this->assertSame(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_flow_max_rounds_reached__the_cap_terminates_the_discussion_without_acceptance(): void
    {
        Event::fake([DiscussionAccepted::class]);

        $attempt = $this->attempt(maxRounds: 2);
        $service = $this->service();

        $this->fake()->willReturn($this->continue('Still not convinced.'));
        $session = $service->start($attempt, 'Opening.', 'mentor');
        $this->assertSame(DiscussionStatus::Active, $session->status); // round 1 of 2, not capped yet

        $this->fake()->willReturn($this->continue('Still need more.'));
        $session = $service->respond($session, 'Second attempt.');

        $this->assertSame(DiscussionStatus::MaxRoundsReached, $session->status);
        $this->assertSame(2, $session->round_count);
        $this->assertNotNull($session->ended_at);

        Event::assertNotDispatched(DiscussionAccepted::class);
        $this->assertNull($session->fresh()->outcome_summary);
        $this->assertSame(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_flow_discussion_unavailable__chain_exhaustion_propagates_and_leaves_nothing_persisted(): void
    {
        Event::fake([DiscussionAccepted::class]);

        $attempt = $this->attempt(maxRounds: 6);
        $this->fake()->willThrow(new NoLlmProviderAvailableException(
            'No configured LLM provider tier could serve this request.'
        ));

        try {
            $this->service()->start($attempt, 'Opening position.', 'mentor');
            $this->fail('Expected NoLlmProviderAvailableException was not thrown.');
        } catch (NoLlmProviderAvailableException) {
            // expected — DiscussionService does not catch/swallow this
            // (§1.4.5: the controller, a later phase, is what turns it
            // into the "temporarily unavailable" UI state; this service
            // layer just needs to leave nothing broken behind)
        }

        $this->assertSame(0, DiscussionSession::count());
        $this->assertDatabaseCount('discussion_turns', 0);
        Event::assertNotDispatched(DiscussionAccepted::class);

        // Diagnosis submission through the normal Version 1 path is
        // completely unaffected — the attempt itself never saw any of this.
        $this->assertSame(AttemptStatus::InProgress, $attempt->fresh()->status);
    }

    public function test_flow_leakage_rejection__a_leak_on_a_later_round_still_rolls_back_cleanly(): void
    {
        // DiscussionServiceTest already proves this for the very first AI
        // turn; this proves it holds mid-conversation too, after prior
        // rounds have already been legitimately persisted.
        Event::fake([DiscussionAccepted::class]);

        $modelSolution = 'The checkout controller has no timeout configured on the gateway call.';
        $attempt = $this->attempt(maxRounds: 6, modelSolution: $modelSolution);
        $service = $this->service();

        $this->fake()->willReturn($this->continue('What in the evidence supports that?'));
        $session = $service->start($attempt, 'Opening position.', 'mentor');
        $turnsBefore = $session->turns()->count();
        $roundCountBefore = $session->round_count;

        $this->fake()->willReturn($this->continue(
            'Think about it: '.$modelSolution.' Does that match?'
        ));

        try {
            $service->respond($session, 'My reply.');
            $this->fail('Expected LeakedReplyException was not thrown.');
        } catch (LeakedReplyException) {
            // expected
        }

        $session->refresh();

        // Rolled back to exactly where it was before this attempt — the
        // earlier, legitimate turns are untouched, but nothing from the
        // failed round (neither the student's new message nor the AI's
        // leaking reply) was persisted.
        $this->assertSame(DiscussionStatus::Active, $session->status);
        $this->assertSame($roundCountBefore, $session->round_count);
        $this->assertSame($turnsBefore, $session->turns()->count());
        Event::assertNotDispatched(DiscussionAccepted::class);
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

    private function service(): DiscussionService
    {
        return app(DiscussionService::class);
    }
}
