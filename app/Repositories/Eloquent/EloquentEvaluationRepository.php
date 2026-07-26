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
}
