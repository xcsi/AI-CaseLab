<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;
use App\Repositories\Contracts\EvaluationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ManualReviewService
{
    public function __construct(
        private readonly EvaluationRepositoryInterface $evaluations,
        private readonly EvaluationService $evaluationService,
    ) {}

    /**
     * Records an instructor's per-criterion scores/comments and finalizes
     * the evaluation. Score aggregation is not reimplemented here —
     * EvaluationService::recalculateTotals() (the same method the initial
     * auto-evaluation uses) does the summing, so there is exactly one
     * place that knows how to total an evaluation.
     *
     * @param  array<int, array{score: float, comment: ?string}>  $criterionReviews  keyed by evaluation_criterion_results.id
     */
    public function submitReview(
        Evaluation $evaluation,
        User $reviewer,
        array $criterionReviews,
        ?string $overallComment,
    ): Evaluation {
        return DB::transaction(function () use ($evaluation, $reviewer, $criterionReviews, $overallComment) {
            foreach ($evaluation->criterionResults as $result) {
                if (! array_key_exists($result->id, $criterionReviews)) {
                    continue;
                }

                $result->update([
                    'instructor_score' => $criterionReviews[$result->id]['score'],
                    'instructor_comment' => $criterionReviews[$result->id]['comment'] ?? null,
                ]);
            }

            $this->evaluations->update($evaluation, [
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'instructor_comment' => $overallComment,
            ]);

            return $this->evaluationService->recalculateTotals($evaluation->fresh('criterionResults'));
        });
    }
}
