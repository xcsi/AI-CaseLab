<?php

namespace Tests\Feature\Discussion;

use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Diagnosis;
use App\Models\DiscussionSession;
use App\Models\DiscussionTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the "Engineering Discussion" section on Performance Review for
 * Phase 19 Milestone 3, per docs/13-ai-discussion-engine-design.md §11.2:
 * renders correctly for each outcome type (accepted / ended-by-student /
 * max-rounds-reached / no discussion at all). Milestone 1 built the
 * section itself; this milestone is its dedicated test coverage, per the
 * roadmap's own split for Phase 19.
 */
class PerformanceReviewDiscussionSectionTest extends TestCase
{
    use RefreshDatabase;

    private function attemptWithDiagnosis(): CaseAttempt
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $this->actingAs($student);

        return $attempt;
    }

    private function createDiscussionSession(CaseAttempt $attempt, DiscussionStatus $status, int $roundCount = 2): DiscussionSession
    {
        $session = DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
            'persona' => 'mentor',
            'status' => $status->value,
            'round_count' => $roundCount,
        ]);

        DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 1,
            'role' => DiscussionTurnRole::Student->value,
            'content' => 'I think the database connection pool was exhausted.',
        ]);
        DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 2,
            'role' => DiscussionTurnRole::Ai->value,
            'content' => 'What evidence supports that specifically?',
        ]);

        return $session;
    }

    public function test_it_renders_the_accepted_outcome(): void
    {
        $attempt = $this->attemptWithDiagnosis();
        $this->createDiscussionSession($attempt, DiscussionStatus::Accepted, roundCount: 3);

        $response = $this->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('Engineering Discussion');
        $response->assertSee('Mentor Review');
        $response->assertSee('Accepted');
        $response->assertSee('I think the database connection pool was exhausted.');
        $response->assertSee('What evidence supports that specifically?');
    }

    public function test_it_renders_the_ended_by_student_outcome(): void
    {
        $attempt = $this->attemptWithDiagnosis();
        $this->createDiscussionSession($attempt, DiscussionStatus::EndedByStudent);

        $response = $this->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('Engineering Discussion');
        $response->assertSee('Ended by Student');
    }

    public function test_it_renders_the_max_rounds_reached_outcome(): void
    {
        $attempt = $this->attemptWithDiagnosis();
        $this->createDiscussionSession($attempt, DiscussionStatus::MaxRoundsReached, roundCount: 8);

        $response = $this->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('Engineering Discussion');
        $response->assertSee('Max Rounds Reached');
        $response->assertSee('8');
    }

    public function test_it_omits_the_section_entirely_when_no_discussion_was_ever_started(): void
    {
        $attempt = $this->attemptWithDiagnosis();

        $response = $this->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('Engineering Discussion');
        $response->assertDontSee('id="discussion-review-collapse"', false);
    }

    public function test_the_section_is_collapsed_by_default(): void
    {
        $attempt = $this->attemptWithDiagnosis();
        $this->createDiscussionSession($attempt, DiscussionStatus::Accepted);

        $response = $this->get(route('performance-review.show', $attempt));

        $response->assertOk();
        // Bootstrap's collapse starts closed unless the "show" class is
        // also present — asserting its absence proves the section isn't
        // pre-expanded.
        $response->assertDontSee('class="collapse show" id="discussion-review-collapse"', false);
        $response->assertSee('class="collapse" id="discussion-review-collapse"', false);
    }

    public function test_it_uses_the_interviewer_personas_workplace_label(): void
    {
        $attempt = $this->attemptWithDiagnosis();
        DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
            'persona' => 'interviewer',
            'status' => DiscussionStatus::MaxRoundsReached->value,
        ]);

        $response = $this->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('Technical Interview');
    }
}
