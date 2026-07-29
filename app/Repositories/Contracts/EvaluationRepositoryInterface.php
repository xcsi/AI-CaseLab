<?php

namespace App\Repositories\Contracts;

use App\Models\Evaluation;

interface EvaluationRepositoryInterface
{
    public function find(int $id): ?Evaluation;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Evaluation;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Evaluation $evaluation, array $data): Evaluation;

    public function delete(Evaluation $evaluation): bool;

    /**
     * Raw percentage scores (0-100) for every evaluation with a positive
     * max_score — the raw material AnalyticsService buckets into a score
     * distribution. Null $caseIds means platform-wide.
     *
     * @param  array<int, int>|null  $caseIds
     * @return array<int, float>
     */
    public function scorePercentages(?array $caseIds = null): array;
}
