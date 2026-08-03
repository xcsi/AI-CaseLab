<?php

namespace Tests\Feature;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Diagnosis;
use App\Models\Evaluation;
use App\Models\EvaluationCriterionResult;
use App\Models\Hint;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design System v1, Milestone 6 — a single outline-stroke SVG icon system
 * (24px grid, 1.5px stroke, `stroke="currentColor"`) replaces every ad hoc
 * HTML-entity glyph and the hint-lock emoji across the app.
 */
class IconSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_locked_hint_shows_the_svg_lock_icon_not_the_emoji(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        Hint::factory()->create(['case_id' => $case->id]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('&#128274;', false);
        $response->assertSee('<span class="hint-lock-icon"><svg', false);
        $response->assertSee('stroke="currentColor"', false);
    }

    public function test_the_performance_review_criterion_states_render_svg_icons(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        $evaluation = Evaluation::create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => 30,
            'max_score' => 30,
            'strategy_used' => 'RuleBasedStrategy',
            'evaluated_at' => now(),
        ]);
        $criterion = RubricCriterion::factory()->create(['case_id' => $case->id]);
        EvaluationCriterionResult::create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $criterion->id,
            'score_awarded' => 30,
            'max_score' => 30,
            'feedback_text' => 'Fully met.',
        ]);

        $response = $this->actingAs($student)->get(route('performance-review.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('&check;', false);
        $response->assertDontSee('&#10007;', false);
        $response->assertDontSee('&#9680;', false);
        $response->assertDontSee('&#9662;', false);
        $response->assertSee('text-success', false);
        $response->assertSee('<svg', false);
    }

    public function test_the_catalog_score_badge_uses_the_svg_check_icon(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create();
        $attempt = CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $case->id,
            'status' => \App\Enums\AttemptStatus::Completed,
            'score_earned' => 8,
            'max_possible_score' => 10,
        ]);

        $response = $this->actingAs($student)->get(route('cases.index'));

        $response->assertOk();
        $response->assertDontSee('&#10003;', false);
        $response->assertSee('80% <svg', false);
    }
}
