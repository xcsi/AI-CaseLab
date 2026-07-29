<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\EvidenceItem;
use App\Models\Hint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 12 (roadmap "Testing & Deployment"): continuous, HTTP-level tests
 * for the three core flows named in the roadmap — admin authors a case,
 * student completes a case, evaluation scores correctly — plus cross-user
 * isolation across two full, independently-completed journeys. These
 * complement (not replace) the many narrower per-controller feature tests
 * already in the suite: nothing else currently proves that one step's real
 * HTTP response actually feeds correctly into the next step's precondition
 * across the whole journey.
 *
 * Evidence authoring has no admin UI (Phase 7 was never built), so these
 * tests attach evidence via factory, matching the convention already used
 * everywhere else in the suite (e.g. EvaluationEngineTest, CaseManagementTest).
 */
class EndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_author_and_publish_a_case_end_to_end(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        // 1. Create the category the case will live under.
        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Networking',
            'slug' => 'networking',
            'description' => 'Connectivity and DNS incidents.',
        ])->assertRedirect();
        $categoryId = \App\Models\Category::where('slug', 'networking')->firstOrFail()->id;

        // 2. Author the case itself.
        $this->actingAs($admin)->post('/admin/cases', [
            'category_id' => $categoryId,
            'title' => 'DNS Resolution Failure',
            'slug' => 'dns-resolution-failure',
            'summary' => 'Customers cannot resolve the API hostname.',
            'ticket_content' => 'Users report ETIMEDOUT errors resolving api.example.com since this morning.',
            'difficulty' => 'medium',
            'estimated_minutes' => 30,
            'model_solution_summary' => 'A stale DNS record pointed at a decommissioned load balancer.',
            'allow_reattempt' => '1',
        ])->assertRedirect();
        $case = CaseModel::where('slug', 'dns-resolution-failure')->firstOrFail();
        $this->assertSame(CaseStatus::Draft, $case->status);

        // 3. Publishing is blocked with no rubric criterion yet — the UI's
        // disabled-button state mirrors this same backend invariant.
        $this->actingAs($admin)->post(route('admin.cases.publish', $case))
            ->assertSessionHasErrors('publish');
        $this->assertSame(CaseStatus::Draft, $case->fresh()->status);

        // 4. Add a hint.
        $this->actingAs($admin)->post(route('admin.cases.hints.store', $case), [
            'content' => 'Check the DNS TTL and the load balancer\'s current IP.',
            'score_penalty' => 5,
        ])->assertRedirect();
        $this->assertDatabaseHas('hints', ['case_id' => $case->id, 'score_penalty' => 5]);

        // 5. Add a rubric criterion — this is the invariant that was blocking publish.
        $this->actingAs($admin)->post(route('admin.cases.rubric-criteria.store', $case), [
            'title' => 'Identifies the stale DNS record as root cause',
            'weight' => 20,
            'matching_type' => 'keyword',
            'keywords' => "DNS\nstale record",
        ])->assertRedirect();
        $this->assertSame('20.00', $case->fresh()->max_score);

        // 6. Publish now succeeds.
        $this->actingAs($admin)->post(route('admin.cases.publish', $case))
            ->assertSessionHas('status');
        $this->assertSame(CaseStatus::Published, $case->fresh()->status);

        // 7. The published case is now visible in the student catalog.
        $this->get(route('cases.index'))->assertOk()->assertSee('DNS Resolution Failure');
    }

    public function test_a_student_can_complete_a_case_end_to_end_and_receive_a_correct_score(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create(['max_score' => 0]);
        $evidenceItem = EvidenceItem::factory()->create(['case_id' => $case->id, 'title' => 'Nginx access log']);
        $hint = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);
        \App\Models\RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 15,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        \App\Models\RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 10,
            'matching_type' => 'evidence_citation',
            'expected_data' => ['required_evidence_ids' => [$evidenceItem->id]],
        ]);
        $case->recalculateMaxScore();
        $this->assertSame('25.00', $case->fresh()->max_score);

        // 1. Browse the catalog — the case is there.
        $this->actingAs($student)->get(route('cases.index'))->assertOk()->assertSee($case->title);

        // 2. Open the case details page.
        $this->actingAs($student)->get(route('cases.show', $case))->assertOk()->assertSee('Start Investigation');

        // 3. Start the investigation.
        $this->actingAs($student)->post(route('attempts.store', $case))
            ->assertRedirect();
        $attempt = CaseAttempt::where('user_id', $student->id)->where('case_id', $case->id)->firstOrFail();
        $this->assertEquals(AttemptStatus::InProgress, $attempt->status);
        $this->assertEquals(25.0, (float) $attempt->max_possible_score);

        // 4. Open the workspace.
        $this->actingAs($student)->get(route('investigation.show', $attempt))
            ->assertOk()->assertSee('Nginx access log');

        // 5. View the evidence item.
        $this->actingAs($student)->postJson(route('investigation.evidence.view', [$attempt, $evidenceItem]))
            ->assertOk();
        $this->assertDatabaseHas('evidence_views', ['case_attempt_id' => $attempt->id, 'evidence_item_id' => $evidenceItem->id]);

        // 6. Save a note.
        $this->actingAs($student)->patchJson(route('investigation.notes.update', $attempt), [
            'content' => 'The nginx log shows repeated upstream timeouts.',
        ])->assertOk();
        $this->assertDatabaseHas('investigation_notes', ['case_attempt_id' => $attempt->id, 'content' => 'The nginx log shows repeated upstream timeouts.']);

        // 7. Unlock the hint — the penalty reduces the ceiling this attempt can still reach.
        $this->actingAs($student)->postJson(route('investigation.hints.unlock', [$attempt, $hint]))
            ->assertOk();
        $attempt->refresh();
        $this->assertEquals(20.0, (float) $attempt->max_possible_score);

        // 8. Submit the diagnosis — this triggers the Evaluation Engine synchronously.
        $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), [
            'root_cause_text' => 'A repeated upstream timeout on the payments service.',
            'proposed_fix_text' => 'Add a circuit breaker and increase the timeout.',
            'confidence_level' => 'high',
            'cited_evidence_ids' => [$evidenceItem->id],
        ])->assertRedirect(route('performance-review.show', $attempt));

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Completed, $attempt->status);

        // 9. The score reflects full marks on both criteria (15 + 10 = 25 raw),
        // but capped at the hint-adjusted ceiling of 20 — proving rubric
        // matching and the hint economy compose correctly end to end.
        $evaluation = $attempt->evaluation;
        $this->assertNotNull($evaluation);
        $this->assertSame(20.0, (float) $evaluation->total_score);
        $this->assertSame(20.0, (float) $evaluation->max_score);
        $this->assertEquals(20.0, (float) $attempt->fresh()->score_earned);

        // 10. The Performance Review page shows that same score.
        $this->actingAs($student)->get(route('performance-review.show', $attempt))
            ->assertOk()
            ->assertSeeText('20 / 20');
    }

    public function test_two_students_completing_the_same_case_cannot_see_each_others_data(): void
    {
        $studentA = User::factory()->create(['name' => 'Student A']);
        $studentB = User::factory()->create(['name' => 'Student B']);
        $case = CaseModel::factory()->published()->create(['max_score' => 0]);
        \App\Models\RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 10,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        $case->recalculateMaxScore();

        // Student A completes the case with a matching diagnosis (full marks).
        $this->actingAs($studentA)->post(route('attempts.store', $case));
        $attemptA = CaseAttempt::where('user_id', $studentA->id)->firstOrFail();
        $this->actingAs($studentA)->patchJson(route('investigation.notes.update', $attemptA), [
            'content' => 'Student A private note — not for anyone else to see.',
        ]);
        $this->actingAs($studentA)->post(route('investigation.diagnosis.store', $attemptA), [
            'root_cause_text' => 'A timeout on the upstream service.',
            'proposed_fix_text' => 'Add a retry with backoff.',
            'confidence_level' => 'high',
        ]);

        // Student B completes the same case with a non-matching diagnosis (zero marks).
        $this->actingAs($studentB)->post(route('attempts.store', $case));
        $attemptB = CaseAttempt::where('user_id', $studentB->id)->firstOrFail();
        $this->actingAs($studentB)->post(route('investigation.diagnosis.store', $attemptB), [
            'root_cause_text' => 'A database connection was refused.',
            'proposed_fix_text' => 'Restart the connection pool.',
            'confidence_level' => 'low',
        ]);

        // Neither student can reach the other's workspace, notes, or review.
        $this->actingAs($studentB)->get(route('investigation.show', $attemptA))->assertForbidden();
        $this->actingAs($studentA)->get(route('investigation.show', $attemptB))->assertForbidden();
        $this->actingAs($studentB)->get(route('performance-review.show', $attemptA))->assertForbidden();
        $this->actingAs($studentA)->get(route('performance-review.show', $attemptB))->assertForbidden();
        $this->actingAs($studentB)->patchJson(route('investigation.notes.update', $attemptA), ['content' => 'intrusion'])
            ->assertForbidden();

        // Each student's own review shows their own result and the case
        // average (both attempts now exist), but never the other student's
        // name, note, or diagnosis text.
        $responseA = $this->actingAs($studentA)->get(route('performance-review.show', $attemptA));
        $responseA->assertOk();
        $responseA->assertSeeText('10 / 10');
        $responseA->assertDontSee('Student B');
        $responseA->assertDontSee('database connection was refused');

        $responseB = $this->actingAs($studentB)->get(route('performance-review.show', $attemptB));
        $responseB->assertOk();
        $responseB->assertSeeText('0 / 10');
        $responseB->assertDontSee('Student A');
        $responseB->assertDontSee('Student A private note');
        $responseB->assertSeeText('Below');
    }
}
