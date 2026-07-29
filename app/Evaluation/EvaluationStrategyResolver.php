<?php

namespace App\Evaluation;

use App\Enums\MatchingType;
use App\Evaluation\Contracts\EvaluationStrategyInterface;
use App\Evaluation\Strategies\EvidenceCitationStrategy;
use App\Evaluation\Strategies\KeywordMatchStrategy;
use App\Evaluation\Strategies\ManualReviewStrategy;

/**
 * Factory: matching_type -> strategy instance. The only place that knows
 * which concrete strategy handles which matching type — EvaluationService
 * never branches on matching_type itself (Open/Closed: a new matching
 * type is a new strategy class + one match arm here, nothing else changes).
 */
class EvaluationStrategyResolver
{
    public function __construct(
        private readonly KeywordMatchStrategy $keywordMatchStrategy,
        private readonly EvidenceCitationStrategy $evidenceCitationStrategy,
        private readonly ManualReviewStrategy $manualReviewStrategy,
    ) {}

    public function resolve(MatchingType $matchingType): EvaluationStrategyInterface
    {
        return match ($matchingType) {
            MatchingType::Keyword => $this->keywordMatchStrategy,
            MatchingType::EvidenceCitation => $this->evidenceCitationStrategy,
            MatchingType::Manual => $this->manualReviewStrategy,
        };
    }
}
