<?php

namespace App\Evaluation\Strategies;

use App\Evaluation\Contracts\EvaluationStrategyInterface;
use App\Evaluation\CriterionResult;
use App\Models\Diagnosis;
use App\Models\RubricCriterion;

/**
 * Stub for `matching_type: manual` — there is no instructor review
 * workflow yet, so this never awards a score. It flags the result as
 * pending instead: EvaluationService excludes pending criteria from the
 * evaluation's total/max (their weight would otherwise permanently cap
 * the score for any case that uses one), and Performance Review renders
 * them as "Awaiting instructor review" rather than a failing score.
 */
class ManualReviewStrategy implements EvaluationStrategyInterface
{
    public function evaluate(RubricCriterion $criterion, Diagnosis $diagnosis): CriterionResult
    {
        return new CriterionResult(
            scoreAwarded: 0.0,
            maxScore: (float) $criterion->weight,
            feedbackText: 'Awaiting instructor review.',
            pendingManualReview: true,
        );
    }
}
