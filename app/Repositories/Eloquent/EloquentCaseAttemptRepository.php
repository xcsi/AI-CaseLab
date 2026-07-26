<?php

namespace App\Repositories\Eloquent;

use App\Models\CaseAttempt;
use App\Repositories\Contracts\CaseAttemptRepositoryInterface;

class EloquentCaseAttemptRepository implements CaseAttemptRepositoryInterface
{
    public function find(int $id): ?CaseAttempt
    {
        return CaseAttempt::find($id);
    }

    public function create(array $data): CaseAttempt
    {
        return CaseAttempt::create($data);
    }

    public function update(CaseAttempt $caseAttempt, array $data): CaseAttempt
    {
        $caseAttempt->update($data);

        return $caseAttempt;
    }

    public function delete(CaseAttempt $caseAttempt): bool
    {
        return (bool) $caseAttempt->delete();
    }
}
