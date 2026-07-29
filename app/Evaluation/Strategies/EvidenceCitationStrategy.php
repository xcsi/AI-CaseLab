<?php

namespace App\Evaluation\Strategies;

use App\Evaluation\Contracts\EvaluationStrategyInterface;
use App\Evaluation\CriterionResult;
use App\Models\Diagnosis;
use App\Models\RubricCriterion;

/**
 * Scores a `matching_type: evidence_citation` criterion by checking how
 * many of its `expected_data.required_evidence_ids` (set by the admin's
 * Rubric Builder) the diagnosis actually cited. Credit is proportional
 * (matched / required * weight). An empty required-ids list — the only
 * value the Rubric Builder can currently produce, since evidence
 * authoring doesn't exist yet — has nothing to check, so it's treated as
 * vacuously satisfied rather than unscoreable.
 */
class EvidenceCitationStrategy implements EvaluationStrategyInterface
{
    public function evaluate(RubricCriterion $criterion, Diagnosis $diagnosis): CriterionResult
    {
        $weight = (float) $criterion->weight;

        $requiredIds = collect($criterion->expected_data['required_evidence_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($requiredIds->isEmpty()) {
            return new CriterionResult(
                scoreAwarded: $weight,
                maxScore: $weight,
                feedbackText: 'No specific evidence citations were required for this criterion.',
            );
        }

        $citedIds = $diagnosis->citedEvidence->pluck('id');
        $matchedIds = $requiredIds->intersect($citedIds)->values();

        $score = round($weight * ($matchedIds->count() / $requiredIds->count()), 2);

        $feedbackText = $matchedIds->isEmpty()
            ? 'None of the required evidence was cited in the diagnosis.'
            : sprintf('Cited %d of %d required evidence item(s).', $matchedIds->count(), $requiredIds->count());

        return new CriterionResult(
            scoreAwarded: $score,
            maxScore: $weight,
            feedbackText: $feedbackText,
            metadata: ['required_evidence_ids' => $requiredIds->all(), 'cited_evidence_ids' => $matchedIds->all()],
        );
    }
}
