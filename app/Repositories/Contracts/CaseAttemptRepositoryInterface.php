<?php

namespace App\Repositories\Contracts;

use App\Models\CaseAttempt;

interface CaseAttemptRepositoryInterface
{
    public function find(int $id): ?CaseAttempt;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CaseAttempt;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CaseAttempt $caseAttempt, array $data): CaseAttempt;

    public function delete(CaseAttempt $caseAttempt): bool;
}
