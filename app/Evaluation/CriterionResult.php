<?php

namespace App\Evaluation;

/**
 * The outcome of one EvaluationStrategyInterface::evaluate() call —
 * everything EvaluationService needs to persist one evaluation_criterion_results
 * row without knowing how the score was actually computed.
 */
final class CriterionResult
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly float $scoreAwarded,
        public readonly float $maxScore,
        public readonly ?string $feedbackText = null,
        public readonly bool $pendingManualReview = false,
        public readonly ?array $metadata = null,
    ) {}
}
