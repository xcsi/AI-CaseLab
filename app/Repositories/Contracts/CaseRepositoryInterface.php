<?php

namespace App\Repositories\Contracts;

use App\Models\CaseModel;

interface CaseRepositoryInterface
{
    public function find(int $id): ?CaseModel;

    public function findBySlug(string $slug): ?CaseModel;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CaseModel;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CaseModel $case, array $data): CaseModel;

    public function delete(CaseModel $case): bool;
}
