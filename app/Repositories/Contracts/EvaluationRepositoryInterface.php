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
}
