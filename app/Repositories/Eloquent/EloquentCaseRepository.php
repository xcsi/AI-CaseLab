<?php

namespace App\Repositories\Eloquent;

use App\Models\CaseModel;
use App\Repositories\Contracts\CaseRepositoryInterface;

class EloquentCaseRepository implements CaseRepositoryInterface
{
    public function find(int $id): ?CaseModel
    {
        return CaseModel::find($id);
    }

    public function findBySlug(string $slug): ?CaseModel
    {
        return CaseModel::where('slug', $slug)->first();
    }

    public function create(array $data): CaseModel
    {
        return CaseModel::create($data);
    }

    public function update(CaseModel $case, array $data): CaseModel
    {
        $case->update($data);

        return $case;
    }

    public function delete(CaseModel $case): bool
    {
        return (bool) $case->delete();
    }
}
