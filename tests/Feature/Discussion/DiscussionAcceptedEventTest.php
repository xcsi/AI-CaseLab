<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use App\Events\DiscussionAccepted;
use App\Listeners\PrefillDiagnosisFromAcceptedDiscussion;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\DiscussionSession;
use App\Models\User;
use App\Services\DiscussionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Proves the DiscussionAccepted event and PrefillDiagnosisFromAcceptedDiscussion
 * listener for Phase 16 Milestone 2, per
 * docs/13-ai-discussion-engine-design.md §1.5/§2.2 — event stays
 * subject-agnostic, all CaseAttempt-specific behavior lives in the
 * listener, DiscussionService itself never branches on subject type.
 */
class DiscussionAcceptedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_a_discussion_fires_the_discussion_accepted_event(): void
    {
        Event::fake([DiscussionAccepted::class]);

        $session = $this->driveToAccepted();

        Event::assertDispatched(
            DiscussionAccepted::class,
            fn (DiscussionAccepted $event) => $event->session->id === $session->id
        );
    }

    public function test_the_real_listener_populates_outcome_summary_when_a_discussion_is_accepted(): void
    {
        // Events are NOT faked here — proves Laravel's auto-discovery
        // actually wires PrefillDiagnosisFromAcceptedDiscussion to
        // DiscussionAccepted (the same convention RecordEvidenceView
        // already relies on for EvidenceViewed), not just that the class
        // compiles.
        $session = $this->driveToAccepted(finalStudentMessage: 'It is a missing timeout on the gateway call.');

        $session->refresh();

        $this->assertNotNull($session->outcome_summary);
        $this->assertStringContainsString('Accepted after', $session->outcome_summary);
        $this->assertStringContainsString('It is a missing timeout on the gateway call.', $session->outcome_summary);
    }

    public function test_the_listener_does_nothing_for_a_session_whose_subject_is_not_a_case_attempt(): void
    {
        // No second subject type exists in Version 2 to test this against
        // for real, so this proves the guard clause itself: given a
        // session attached to some other model, the listener must not
        // touch outcome_summary at all.
        $otherUser = User::factory()->create();
        $session = DiscussionSession::factory()->create([
            'discussable_type' => User::class,
            'discussable_id' => $otherUser->id,
            'outcome_summary' => null,
        ]);

        (new PrefillDiagnosisFromAcceptedDiscussion())->handle(new DiscussionAccepted($session));

        $this->assertNull($session->fresh()->outcome_summary);
    }

    private function driveToAccepted(string $finalStudentMessage = 'It is X, per the evidence.'): DiscussionSession
    {
        $case = CaseModel::factory()->create([
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => 6,
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);

        /** @var FakeLlmClient $fake */
        $fake = app(LlmClientInterface::class);
        $service = app(DiscussionService::class);

        $fake->willReturn(new LlmTurnResult('What supports that?', DiscussionVerdict::Continue));
        $session = $service->start($attempt, 'Opening position.', 'mentor');

        $fake->willReturn(new LlmTurnResult('Accepted — well reasoned.', DiscussionVerdict::Accept));

        return $service->respond($session, $finalStudentMessage);
    }
}
