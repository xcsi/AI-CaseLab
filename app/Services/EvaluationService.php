<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Evaluation\EvaluationStrategyResolver;
use App\Events\CaseAttemptCompleted;
use App\Models\CaseAttempt;
use App\Models\Diagnosis;
use App\Models\Evaluation;
use App\Models\RubricCriterion;
use App\Repositories\Contracts\CaseAttemptRepositoryInterface;
use App\Repositories\Contracts\EvaluationRepositoryInterface;

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

        $gradable = $scored->reject(fn (array $entry) => $entry['result']->pendingManualReview);
        $pendingCount = $scored->count() - $gradable->count();

        $rawMaxScore = (float) $gradable->sum(fn (array $entry) => $entry['result']->maxScore);
        $rawTotalScore = (float) $gradable->sum(fn (array $entry) => $entry['result']->scoreAwarded);

        // The evaluation's ceiling is the smaller of what the rubric can
        // actually award (excluding pending-manual weight) and the
        // attempt's own ceiling, which already accounts for hint penalties
        // (HintUnlockService) — neither alone is the right cap on its own.
        $finalMaxScore = round(min($rawMaxScore, (float) $attempt->max_possible_score), 2);
        $finalTotalScore = $finalMaxScore > 0 ? round(min($rawTotalScore, $finalMaxScore), 2) : 0.0;

        $fullyMetCount = $gradable->filter(
            fn (array $entry) => $entry['result']->maxScore > 0 && $entry['result']->scoreAwarded >= $entry['result']->maxScore
        )->count();

        $evaluatedAt = now();

        $evaluation = $this->evaluations->create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => $finalTotalScore,
            'max_score' => $finalMaxScore,
            'feedback_summary' => $gradable->isEmpty()
                ? null
                : "{$fullyMetCount} of {$gradable->count()} criteria fully met.",
            'strategy_used' => $scored->map(fn (array $entry) => class_basename($entry['strategy']))->unique()->implode(','),
            'metadata' => $pendingCount > 0 ? ['pending_manual_review_count' => $pendingCount] : null,
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

        $this->caseAttempts->update($attempt, [
            'status' => AttemptStatus::Completed,
            'completed_at' => $evaluatedAt,
            'score_earned' => $finalTotalScore,
        ]);

        event(new CaseAttemptCompleted($attempt, $evaluation));

        return $evaluation;
    }
}
