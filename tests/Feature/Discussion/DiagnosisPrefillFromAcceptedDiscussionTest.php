<?php

namespace Tests\Feature\Discussion;

use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\DiscussionSession;
use App\Models\DiscussionTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the accept -> diagnosis-prefill flow for Phase 18 Milestone 3
 * (docs/13-ai-discussion-engine-design.md §11.1): the diagnosis form's
 * root_cause_text pre-fills with the accepted discussion's final student
 * turn, stays empty when no discussion was accepted, and defers to a
 * validation-error old() value over the accepted position. This is the
 * server-side half of the transition moment; the client-side call to
 * action lives in the discussion panel and is covered by
 * WorkspaceDiscussionEndAndAcceptUiTest.
 */
class DiagnosisPrefillFromAcceptedDiscussionTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_cause_text_is_prefilled_with_the_accepted_discussions_final_student_turn(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $session = DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
            'status' => DiscussionStatus::Accepted->value,
        ]);

        DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 1,
            'role' => DiscussionTurnRole::Student->value,
            'content' => 'The database connection pool was exhausted under load.',
        ]);
        DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 2,
            'role' => DiscussionTurnRole::Ai->value,
            'content' => 'What evidence points to the pool specifically?',
        ]);
        DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 3,
            'role' => DiscussionTurnRole::Student->value,
            'content' => 'The connection pool metrics in the API logs show it maxed out at the incident timestamp.',
        ]);

        $response = $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt));

        $response->assertOk();
        $response->assertDontSee('The database connection pool was exhausted under load.');
        $response->assertSee('The connection pool metrics in the API logs show it maxed out at the incident timestamp.');
    }

    public function test_root_cause_text_is_empty_when_no_discussion_was_accepted(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
            'status' => DiscussionStatus::MaxRoundsReached->value,
        ]);

        $response = $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt));

        $response->assertOk();
        $response->assertSee('<textarea', false);
    }

    public function test_a_validation_error_redisplay_keeps_the_students_edited_text_over_the_accepted_position(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $session = DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
            'status' => DiscussionStatus::Accepted->value,
        ]);
        DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 1,
            'role' => DiscussionTurnRole::Student->value,
            'content' => 'Original accepted position from the discussion.',
        ]);

        $this->actingAs($student)
            ->from(route('investigation.diagnosis.create', $attempt))
            ->post(route('investigation.diagnosis.store', $attempt), [
                'root_cause_text' => 'A student-edited root cause the student typed in themselves.',
                // proposed_fix_text omitted deliberately to fail validation
                // and trigger the old()-restored redisplay.
            ])
            ->assertSessionHasErrors('proposed_fix_text');

        $response = $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt));

        $response->assertOk();
        $response->assertSee('A student-edited root cause the student typed in themselves.');
        $response->assertDontSee('Original accepted position from the discussion.');
    }
}
