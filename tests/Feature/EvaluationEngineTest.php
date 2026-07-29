<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Evaluation\Strategies\EvidenceCitationStrategy;
use App\Evaluation\Strategies\KeywordMatchStrategy;
use App\Evaluation\Strategies\ManualReviewStrategy;
use App\Events\CaseAttemptCompleted;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Diagnosis;
use App\Models\EvidenceItem;
use App\Models\HintUnlock;
use App\Models\RubricCriterion;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EvaluationEngineTest extends TestCase
{
    use RefreshDatabase;

    // --- KeywordMatchStrategy -------------------------------------------------

    public function test_keyword_strategy_awards_full_weight_when_all_keywords_match(): void
    {
        $criterion = RubricCriterion::factory()->make([
            'weight' => 30,
            'expected_data' => ['keywords' => ['timeout', 'retry']],
        ]);
        $diagnosis = Diagnosis::factory()->make([
            'root_cause_text' => 'A timeout occurred upstream.',
            'proposed_fix_text' => 'Add a retry with backoff.',
        ]);

        $result = (new KeywordMatchStrategy)->evaluate($criterion, $diagnosis);

        $this->assertSame(30.0, $result->scoreAwarded);
        $this->assertSame(30.0, $result->maxScore);
        $this->assertFalse($result->pendingManualReview);
    }

    public function test_keyword_strategy_awards_proportional_credit_for_a_partial_match(): void
    {
        $criterion = RubricCriterion::factory()->make([
            'weight' => 20,
            'expected_data' => ['keywords' => ['timeout', 'retry', 'circuit breaker', 'backoff']],
        ]);
        $diagnosis = Diagnosis::factory()->make([
            'root_cause_text' => 'A timeout occurred upstream.',
            'proposed_fix_text' => 'Add a retry.',
        ]);

        $result = (new KeywordMatchStrategy)->evaluate($criterion, $diagnosis);

        $this->assertSame(10.0, $result->scoreAwarded);
        $this->assertStringContainsString('Matched 2 of 4', $result->feedbackText);
    }

    public function test_keyword_strategy_awards_zero_when_nothing_matches(): void
    {
        $criterion = RubricCriterion::factory()->make([
            'weight' => 15,
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        $diagnosis = Diagnosis::factory()->make([
            'root_cause_text' => 'The database connection was refused.',
            'proposed_fix_text' => 'Restart the connection pool.',
        ]);

        $result = (new KeywordMatchStrategy)->evaluate($criterion, $diagnosis);

        $this->assertSame(0.0, $result->scoreAwarded);
    }

    // --- EvidenceCitationStrategy ----------------------------------------------

    public function test_evidence_citation_strategy_awards_proportional_credit(): void
    {
        $case = CaseModel::factory()->create();
        $item1 = EvidenceItem::factory()->create(['case_id' => $case->id]);
        $item2 = EvidenceItem::factory()->create(['case_id' => $case->id]);
        $criterion = RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 10,
            'matching_type' => 'evidence_citation',
            'expected_data' => ['required_evidence_ids' => [$item1->id, $item2->id]],
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        $diagnosis->citedEvidence()->attach($item1->id);

        $result = (new EvidenceCitationStrategy)->evaluate($criterion, $diagnosis->fresh());

        $this->assertSame(5.0, $result->scoreAwarded);
    }

    public function test_evidence_citation_strategy_awards_full_credit_when_nothing_is_required(): void
    {
        $criterion = RubricCriterion::factory()->create(['expected_data' => ['required_evidence_ids' => []]]);
        $attempt = CaseAttempt::factory()->create();
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $result = (new EvidenceCitationStrategy)->evaluate($criterion, $diagnosis);

        $this->assertSame((float) $criterion->weight, $result->scoreAwarded);
    }

    // --- ManualReviewStrategy ----------------------------------------------------

    public function test_manual_review_strategy_never_awards_a_score_and_flags_pending(): void
    {
        $criterion = RubricCriterion::factory()->make(['weight' => 25]);
        $diagnosis = Diagnosis::factory()->make();

        $result = (new ManualReviewStrategy)->evaluate($criterion, $diagnosis);

        $this->assertSame(0.0, $result->scoreAwarded);
        $this->assertTrue($result->pendingManualReview);
    }

    // --- EvaluationService ---------------------------------------------------------

    public function test_evaluate_persists_an_evaluation_and_per_criterion_results(): void
    {
        $case = CaseModel::factory()->create(['max_score' => 50]);
        $keywordCriterion = RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 30,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        $citationCriterion = RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 20,
            'matching_type' => 'evidence_citation',
            'expected_data' => ['required_evidence_ids' => []],
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'max_possible_score' => 50]);
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the upstream service.',
        ]);

        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $this->assertSame(50.0, (float) $evaluation->total_score);
        $this->assertSame(50.0, (float) $evaluation->max_score);
        $this->assertCount(2, $evaluation->criterionResults);
        $this->assertDatabaseHas('evaluation_criterion_results', [
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $keywordCriterion->id,
            'score_awarded' => 30,
        ]);
        $this->assertDatabaseHas('evaluation_criterion_results', [
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $citationCriterion->id,
            'score_awarded' => 20,
        ]);
    }

    public function test_manual_review_criteria_are_excluded_from_the_evaluation_total_and_max(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 30,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 20,
            'matching_type' => 'manual',
            'expected_data' => [],
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'max_possible_score' => 50]);
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the upstream service.',
        ]);

        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        // Only the keyword criterion's weight counts toward max — the
        // pending manual criterion is recorded but excluded, so it can't
        // permanently cap the score with no reviewer to ever clear it.
        $this->assertSame(30.0, (float) $evaluation->total_score);
        $this->assertSame(30.0, (float) $evaluation->max_score);
        $this->assertSame(['pending_manual_review_count' => 1], $evaluation->metadata);
    }

    public function test_the_final_score_is_capped_at_the_attempts_hint_adjusted_ceiling(): void
    {
        $case = CaseModel::factory()->create(['max_score' => 100]);
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 100,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        // Simulates a hint penalty already having reduced the ceiling
        // (HintUnlockService), independent of the rubric's own weight sum.
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'max_possible_score' => 92.5]);
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the upstream service.',
        ]);

        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $this->assertSame(92.5, (float) $evaluation->total_score);
        $this->assertSame(92.5, (float) $evaluation->max_score);
    }

    public function test_evaluate_is_idempotent(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $first = app(EvaluationService::class)->evaluate($attempt, $diagnosis);
        $second = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, \App\Models\Evaluation::where('case_attempt_id', $attempt->id)->count());
    }

    public function test_evaluate_marks_the_attempt_completed_and_fires_case_attempt_completed(): void
    {
        Event::fake();

        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Submitted]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Completed, $attempt->status);
        $this->assertNotNull($attempt->completed_at);
        $this->assertEquals($evaluation->total_score, $attempt->score_earned);

        Event::assertDispatched(CaseAttemptCompleted::class, fn ($event) => $event->attempt->is($attempt) && $event->evaluation->is($evaluation));
    }

    public function test_evaluate_does_not_award_more_than_the_hint_reduced_ceiling_even_with_full_marks(): void
    {
        $case = CaseModel::factory()->create(['max_score' => 20]);
        $hint = \App\Models\Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 20,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'max_possible_score' => 15]);
        HintUnlock::create([
            'case_attempt_id' => $attempt->id,
            'hint_id' => $hint->id,
            'penalty_applied' => 5,
            'unlocked_at' => now(),
        ]);
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the upstream service.',
        ]);

        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $this->assertSame(15.0, (float) $evaluation->total_score);
    }

    // --- Manual review model/service helpers -----------------------------------

    public function test_needs_instructor_review_is_true_until_the_pending_criterion_is_scored(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10, 'matching_type' => 'manual', 'expected_data' => []]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);

        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);
        $this->assertTrue($evaluation->fresh(['criterionResults'])->needsInstructorReview());

        $result = $evaluation->criterionResults->first();
        $result->update(['instructor_score' => 5]);

        $this->assertFalse($evaluation->fresh(['criterionResults'])->needsInstructorReview());
    }

    public function test_awaiting_instructor_review_scope_matches_needs_instructor_review(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10, 'matching_type' => 'manual', 'expected_data' => []]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $diagnosis = Diagnosis::factory()->create(['case_attempt_id' => $attempt->id]);
        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $this->assertTrue(\App\Models\Evaluation::awaitingInstructorReview()->whereKey($evaluation->id)->exists());

        $evaluation->criterionResults->first()->update(['instructor_score' => 5]);

        $this->assertFalse(\App\Models\Evaluation::awaitingInstructorReview()->whereKey($evaluation->id)->exists());
    }

    public function test_manual_review_service_recalculates_a_mixed_evaluation_after_review(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 20,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 30,
            'matching_type' => 'manual',
            'expected_data' => [],
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'max_possible_score' => 50]);
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the upstream service.',
        ]);
        $evaluation = app(EvaluationService::class)->evaluate($attempt, $diagnosis);
        // Before review: only the keyword criterion (20/20) counts.
        $this->assertSame(20.0, (float) $evaluation->total_score);
        $this->assertSame(20.0, (float) $evaluation->max_score);

        $manualResult = $evaluation->criterionResults->first(
            fn ($result) => $result->rubricCriterion->matching_type === \App\Enums\MatchingType::Manual
        );
        $reviewer = \App\Models\User::factory()->withRole(\App\Enums\UserRole::Instructor)->create();

        $reviewed = app(\App\Services\ManualReviewService::class)->submitReview(
            $evaluation,
            $reviewer,
            [$manualResult->id => ['score' => 20, 'comment' => 'Reasonable given the ticket.']],
            'Overall solid.',
        );

        $this->assertSame(40.0, (float) $reviewed->total_score);
        $this->assertSame(50.0, (float) $reviewed->max_score);
        $this->assertEquals($reviewer->id, $reviewed->reviewed_by);
        $this->assertSame('Overall solid.', $reviewed->instructor_comment);
        $this->assertEquals(40.0, $attempt->fresh()->score_earned);
    }
}
