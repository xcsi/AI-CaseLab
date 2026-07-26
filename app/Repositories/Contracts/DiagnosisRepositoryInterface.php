<?php

namespace App\Repositories\Contracts;

use App\Models\Diagnosis;

interface DiagnosisRepositoryInterface
{
    public function find(int $id): ?Diagnosis;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Diagnosis;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Diagnosis $diagnosis, array $data): Diagnosis;

    public function delete(Diagnosis $diagnosis): bool;
}
