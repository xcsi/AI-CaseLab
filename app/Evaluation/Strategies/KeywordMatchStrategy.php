<?php

namespace App\Evaluation\Strategies;

use App\Evaluation\Contracts\EvaluationStrategyInterface;
use App\Evaluation\CriterionResult;
use App\Models\Diagnosis;
use App\Models\RubricCriterion;

/**
 * Scores a `matching_type: keyword` criterion by checking how many of its
 * `expected_data.keywords` (set by the admin's Rubric Builder — see
 * StoreRubricCriterionRequest) appear as a case-insensitive substring
 * anywhere in the diagnosis's root cause + proposed fix text. Credit is
 * proportional (matched / total * weight), not all-or-nothing, since a
 * diagnosis that finds half the expected causes deserves partial credit.
 */
class KeywordMatchStrategy implements EvaluationStrategyInterface
{
    public function evaluate(RubricCriterion $criterion, Diagnosis $diagnosis): CriterionResult
    {
        $weight = (float) $criterion->weight;

        $keywords = collect($criterion->expected_data['keywords'] ?? [])
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->values();

        if ($keywords->isEmpty()) {
            return new CriterionResult(
                scoreAwarded: $weight,
                maxScore: $weight,
                feedbackText: 'No keywords were configured for this criterion.',
            );
        }

        $haystack = strtolower($diagnosis->root_cause_text.' '.$diagnosis->proposed_fix_text);

        $matched = $keywords->filter(fn ($keyword) => str_contains($haystack, strtolower($keyword)))->values();

        $score = round($weight * ($matched->count() / $keywords->count()), 2);

        $feedbackText = $matched->isEmpty()
            ? 'None of the expected keywords were found in the diagnosis.'
            : sprintf(
                'Matched %d of %d expected keyword(s): %s.',
                $matched->count(),
                $keywords->count(),
                $matched->implode(', '),
            );

        return new CriterionResult(
            scoreAwarded: $score,
            maxScore: $weight,
            feedbackText: $feedbackText,
            metadata: ['matched_keywords' => $matched->all(), 'expected_keywords' => $keywords->all()],
        );
    }
}
