<?php

namespace App\Repositories\Eloquent;

use App\Models\Evaluation;
use App\Repositories\Contracts\EvaluationRepositoryInterface;

class EloquentEvaluationRepository implements EvaluationRepositoryInterface
{
    public function find(int $id): ?Evaluation
    {
        return Evaluation::find($id);
    }

    public function create(array $data): Evaluation
    {
        return Evaluation::create($data);
    }

    public function update(Evaluation $evaluation, array $data): Evaluation
    {
        $evaluation->update($data);

        return $evaluation;
    }

    public function delete(Evaluation $evaluation): bool
    {
        return (bool) $evaluation->delete();
    }

    public function scorePercentages(?array $caseIds = null): array
    {
        $query = Evaluation::query()->where('max_score', '>', 0);

        if ($caseIds !== null) {
            $query->whereHas('caseAttempt', fn ($q) => $q->whereIn('case_id', $caseIds));
        }

        return $query->get(['total_score', 'max_score'])
            ->map(fn (Evaluation $evaluation) => round(
                (float) $evaluation->total_score / (float) $evaluation->max_score * 100,
                2
            ))
            ->all();
    }
}
