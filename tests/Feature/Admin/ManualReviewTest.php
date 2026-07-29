<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Diagnosis;
use App\Models\Evaluation;
use App\Models\RubricCriterion;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: CaseModel, 1: CaseAttempt, 2: Diagnosis, 3: Evaluation, 4: RubricCriterion}
     */
    private function evaluatedAttemptWithManualCriterion(): array
    {
        $case = CaseModel::factory()->create(['max_score' => 30]);
        $manualCriterion = RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 30,
            'matching_type' => 'manual',
            'expected_data' => [],
        ]);
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'user_id' => $student->id,
            'max_possible_score' => 30,
        ]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        return [$case, $attempt, $diagnosis, $evaluation, $manualCriterion];
    }

    public function test_guest_cannot_view_the_review_queue(): void
    {
        $this->get(route('admin.evaluations.index'))->assertRedirect('/login');
    }

    public function test_student_cannot_view_the_review_queue(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('admin.evaluations.index'))->assertForbidden();
    }

    public function test_admin_can_view_the_review_queue(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.evaluations.index'));

        $response->assertOk();
        $response->assertSee($evaluation->caseAttempt->case->title);
    }

    public function test_instructor_can_view_the_review_queue(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $response = $this->actingAs($instructor)->get(route('admin.evaluations.index'));

        $response->assertOk();
        $response->assertSee($evaluation->caseAttempt->case->title);
    }

    public function test_the_queue_excludes_evaluations_with_nothing_pending(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 10,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id, 'root_cause_text' => 'A timeout occurred.']);
        app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $response = $this->actingAs($admin)->get(route('admin.evaluations.index'));

        $response->assertOk();
        $response->assertSee('Nothing is awaiting review');
    }

    public function test_the_queue_excludes_already_reviewed_evaluations(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $result = $evaluation->criterionResults->first();

        $this->actingAs($admin)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$result->id => ['score' => 25, 'comment' => 'Solid reasoning.']],
            'comment' => 'Good work overall.',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.evaluations.index'));
        $response->assertSee('Nothing is awaiting review');
    }

    public function test_guest_cannot_view_the_review_form(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();

        $this->get(route('admin.evaluations.edit', $evaluation))->assertRedirect('/login');
    }

    public function test_student_cannot_view_the_review_form(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('admin.evaluations.edit', $evaluation))->assertForbidden();
    }

    public function test_the_review_form_shows_the_diagnosis_and_pending_criteria(): void
    {
        [, , $diagnosis, $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.evaluations.edit', $evaluation));

        $response->assertOk();
        $response->assertSee($diagnosis->root_cause_text);
        $response->assertSee('Pending');
    }

    public function test_submitting_a_review_scores_the_pending_criterion_and_recalculates_the_total(): void
    {
        [, $attempt, , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $result = $evaluation->criterionResults->first();

        $response = $this->actingAs($admin)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [
                $result->id => ['score' => 25, 'comment' => 'Solid reasoning.'],
            ],
            'comment' => 'Good work overall.',
        ]);

        $response->assertRedirect(route('admin.evaluations.index'));

        $evaluation->refresh();
        $this->assertEquals(25, $evaluation->total_score);
        $this->assertEquals(30, $evaluation->max_score);
        $this->assertNotNull($evaluation->reviewed_at);
        $this->assertEquals($admin->id, $evaluation->reviewed_by);
        $this->assertEquals('Good work overall.', $evaluation->instructor_comment);
        $this->assertNull($evaluation->metadata);

        $result->refresh();
        $this->assertEquals(25, $result->instructor_score);
        $this->assertEquals('Solid reasoning.', $result->instructor_comment);
        // The original strategy output is never overwritten.
        $this->assertEquals(0, $result->score_awarded);

        $attempt->refresh();
        $this->assertEquals(25, $attempt->score_earned);
    }

    public function test_score_cannot_exceed_the_criterions_max_score(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $result = $evaluation->criterionResults->first();

        $response = $this->actingAs($admin)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$result->id => ['score' => 999]],
        ]);

        $response->assertSessionHasErrors("criteria.{$result->id}.score");
        $this->assertNull($result->fresh()->instructor_score);
    }

    public function test_score_is_required(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [],
        ]);

        $response->assertSessionHasErrors('criteria');
    }

    public function test_a_criterion_id_from_another_evaluation_is_rejected(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        [, , , $otherEvaluation] = $this->evaluatedAttemptWithManualCriterion();
        $foreignResult = $otherEvaluation->criterionResults->first();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$foreignResult->id => ['score' => 10]],
        ]);

        $response->assertSessionHasErrors("criteria.{$foreignResult->id}.score");
    }

    public function test_instructor_can_submit_a_review(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();
        $result = $evaluation->criterionResults->first();

        $response = $this->actingAs($instructor)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$result->id => ['score' => 30]],
        ]);

        $response->assertRedirect(route('admin.evaluations.index'));
        $this->assertEquals($instructor->id, $evaluation->fresh()->reviewed_by);
    }

    public function test_guest_cannot_submit_a_review(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $result = $evaluation->criterionResults->first();

        $response = $this->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$result->id => ['score' => 30]],
        ]);

        $response->assertRedirect('/login');
        $this->assertNull($result->fresh()->instructor_score);
    }

    public function test_student_cannot_submit_a_review(): void
    {
        [, , , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $student = User::factory()->create();
        $result = $evaluation->criterionResults->first();

        $response = $this->actingAs($student)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$result->id => ['score' => 30]],
        ]);

        $response->assertForbidden();
        $this->assertNull($result->fresh()->instructor_score);
    }

    public function test_the_reviewed_score_is_reflected_on_the_students_performance_review(): void
    {
        [, $attempt, , $evaluation] = $this->evaluatedAttemptWithManualCriterion();
        $student = $attempt->user;
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $result = $evaluation->criterionResults->first();

        $this->actingAs($admin)->put(route('admin.evaluations.update', $evaluation), [
            'criteria' => [$result->id => ['score' => 25, 'comment' => 'Solid reasoning.']],
            'comment' => 'Nicely done.',
        ]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSeeText('25 / 30');
        $response->assertSee('Nicely done.');
        $response->assertSee('Solid reasoning.');
        $response->assertDontSee('awaiting instructor review');
    }
}
