<?php

namespace Tests\Feature\Student;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Diagnosis;
use App\Models\Evaluation;
use App\Models\EvaluationCriterionResult;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $this->get(route('performance-review.show', $attempt))->assertRedirect('/login');
    }

    public function test_a_different_student_cannot_view_the_review(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('performance-review.show', $attempt))->assertForbidden();
    }

    public function test_visiting_before_a_diagnosis_was_submitted_redirects_to_the_workspace(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertRedirect(route('investigation.show', $attempt));
    }

    public function test_visiting_a_submitted_but_unevaluated_attempt_evaluates_it_on_the_spot(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'title' => 'Identifies timeout as root cause',
            'weight' => 30,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the payment gateway caused the failure.',
        ]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('evaluation is still pending');
        $response->assertSee('Per-Criterion Breakdown');
        $response->assertSee('Identifies timeout as root cause');

        $this->assertDatabaseHas('evaluations', ['case_attempt_id' => $attempt->id]);
        $attempt->refresh();
        $this->assertEquals(\App\Enums\AttemptStatus::Completed, $attempt->status);
    }

    public function test_it_shows_an_awaiting_evaluation_state_when_the_case_has_no_rubric_criteria(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);
        Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('Per-Criterion Breakdown');
    }

    public function test_it_shows_the_score_and_per_criterion_breakdown_when_evaluated(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        $evaluation = Evaluation::create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => 78,
            'max_score' => 100,
            'strategy_used' => 'RuleBasedStrategy',
            'evaluated_at' => now(),
        ]);
        $criterion = RubricCriterion::factory()->create(['case_id' => $case->id, 'title' => 'Identifies timeout as root cause']);
        EvaluationCriterionResult::create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $criterion->id,
            'score_awarded' => 30,
            'max_score' => 30,
            'feedback_text' => 'Correctly identified the timeout.',
        ]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('78');
        $response->assertSee('100');
        $response->assertSee('78%');
        $response->assertSee('Per-Criterion Breakdown');
        $response->assertSee('Identifies timeout as root cause');
        $response->assertSee('Correctly identified the timeout.');
    }

    public function test_the_case_average_is_hidden_when_this_is_the_only_evaluated_attempt(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        Evaluation::create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => 78,
            'max_score' => 100,
            'strategy_used' => 'RuleBasedStrategy',
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('case average');
    }

    public function test_the_case_average_is_shown_when_another_evaluated_attempt_exists(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $case = CaseModel::factory()->create();

        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        Evaluation::create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => 90,
            'max_score' => 100,
            'strategy_used' => 'RuleBasedStrategy',
            'evaluated_at' => now(),
        ]);

        $otherAttempt = CaseAttempt::factory()->create(['user_id' => $otherStudent->id, 'case_id' => $case->id]);
        $otherDiagnosis = Diagnosis::factory()->create(['case_attempt_id' => $otherAttempt->id]);
        Evaluation::create([
            'case_attempt_id' => $otherAttempt->id,
            'diagnosis_id' => $otherDiagnosis->id,
            'total_score' => 60,
            'max_score' => 100,
            'strategy_used' => 'RuleBasedStrategy',
            'evaluated_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSeeInOrder(['Above', 'case average (75)']);
    }

    public function test_the_model_solution_summary_is_shown_when_present(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['model_solution_summary' => 'Add retry with exponential backoff.']);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('What Actually Happened');
        $response->assertSee('Add retry with exponential backoff.');
    }

    public function test_the_back_to_incidents_link_is_present(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);
        Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('href="' . route('cases.index') . '"', false);
    }

    public function test_reattempt_button_posts_to_attempts_store_when_reattempt_is_allowed(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['allow_reattempt' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertSee('action="' . route('attempts.store', $case) . '"', false);
    }

    public function test_reattempt_button_is_disabled_when_reattempt_is_not_allowed(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['allow_reattempt' => false]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('action="' . route('attempts.store', $case) . '"', false);
        $response->assertSee('This incident does not allow reattempts.', false);
    }
}
