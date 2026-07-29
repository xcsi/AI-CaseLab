<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Evaluation\EvaluationStrategyResolver;
use App\Events\CaseAttemptCompleted;
use App\Models\CaseAttempt;
use App\Models\Diagnosis;
use App\Models\Evaluation;
use App\Models\EvaluationCriterionResult;
use App\Models\RubricCriterion;
use App\Repositories\Contracts\CaseAttemptRepositoryInterface;
use App\Repositories\Contracts\EvaluationRepositoryInterface;
use Illuminate\Support\Collection;

class EvaluationService
{
    public function __construct(
        private readonly EvaluationStrategyResolver $strategies,
        private readonly EvaluationRepositoryInterface $evaluations,
        private readonly CaseAttemptRepositoryInterface $caseAttempts,
    ) {}

    /**
     * Idempotent: an attempt can only ever have one evaluation
     * (case_attempts.id is unique on evaluations), so calling this again
     * for an already-evaluated attempt returns the existing evaluation
     * instead of erroring.
     */
    public function evaluate(CaseAttempt $attempt, Diagnosis $diagnosis): Evaluation
    {
        $existing = $attempt->evaluation()->first();

        if ($existing) {
            return $existing;
        }

        $scored = $attempt->case->rubricCriteria->map(fn (RubricCriterion $criterion) => [
            'criterion' => $criterion,
            'strategy' => $this->strategies->resolve($criterion->matching_type),
        ])->map(fn (array $entry) => [
            'criterion' => $entry['criterion'],
            'strategy' => $entry['strategy'],
            'result' => $entry['strategy']->evaluate($entry['criterion'], $diagnosis),
        ]);

        $evaluatedAt = now();

        $evaluation = $this->evaluations->create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => 0,
            'max_score' => 0,
            'strategy_used' => $scored->map(fn (array $entry) => class_basename($entry['strategy']))->unique()->implode(','),
            'evaluated_at' => $evaluatedAt,
        ]);

        foreach ($scored as $entry) {
            $evaluation->criterionResults()->create([
                'rubric_criterion_id' => $entry['criterion']->id,
                'score_awarded' => $entry['result']->scoreAwarded,
                'max_score' => $entry['result']->maxScore,
                'feedback_text' => $entry['result']->feedbackText,
                'metadata' => $entry['result']->pendingManualReview
                    ? ['pending_manual_review' => true]
                    : $entry['result']->metadata,
            ]);
        }

        $this->recalculateTotals($evaluation->fresh('criterionResults'));

        $this->caseAttempts->update($attempt, [
            'status' => AttemptStatus::Completed,
            'completed_at' => $evaluatedAt,
        ]);

        $evaluation = $evaluation->fresh(['criterionResults']);

        event(new CaseAttemptCompleted($attempt, $evaluation));

        return $evaluation;
    }

    /**
     * Recomputes an evaluation's total_score/max_score/feedback_summary
     * from its current criterion results and keeps the attempt's
     * score_earned in sync — the single place this summation happens, so
     * both the initial auto-evaluation and a later instructor review
     * (ManualReviewService) go through the same math instead of each
     * re-implementing it.
     */
    public function recalculateTotals(Evaluation $evaluation): Evaluation
    {
        $evaluation->loadMissing(['criterionResults', 'caseAttempt']);

        /** @var Collection<int, EvaluationCriterionResult> $results */
        $results = $evaluation->criterionResults;
        $gradable = $results->reject(fn (EvaluationCriterionResult $result) => $result->isPendingManualReview());
        $pendingCount = $results->count() - $gradable->count();

        $rawMaxScore = (float) $gradable->sum(fn (EvaluationCriterionResult $result) => (float) $result->max_score);
        $rawTotalScore = (float) $gradable->sum(fn (EvaluationCriterionResult $result) => $result->effectiveScore());

        // The evaluation's ceiling is the smaller of what the rubric can
        // actually award (excluding still-pending weight) and the
        // attempt's own ceiling, which already accounts for hint penalties
        // (HintUnlockService) — neither alone is the right cap on its own.
        $finalMaxScore = round(min($rawMaxScore, (float) $evaluation->caseAttempt->max_possible_score), 2);
        $finalTotalScore = $finalMaxScore > 0 ? round(min($rawTotalScore, $finalMaxScore), 2) : 0.0;

        $fullyMetCount = $gradable->filter(
            fn (EvaluationCriterionResult $result) => (float) $result->max_score > 0
                && $result->effectiveScore() >= (float) $result->max_score
        )->count();

        $this->evaluations->update($evaluation, [
            'total_score' => $finalTotalScore,
            'max_score' => $finalMaxScore,
            'feedback_summary' => $gradable->isEmpty()
                ? null
                : "{$fullyMetCount} of {$gradable->count()} criteria fully met.",
            'metadata' => $pendingCount > 0 ? ['pending_manual_review_count' => $pendingCount] : null,
        ]);

        $this->caseAttempts->update($evaluation->caseAttempt, ['score_earned' => $finalTotalScore]);

        return $evaluation;
    }
}
