<?php

namespace App\Repositories\Eloquent;

use App\Models\Diagnosis;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;

class EloquentDiagnosisRepository implements DiagnosisRepositoryInterface
{
    public function find(int $id): ?Diagnosis
    {
        return Diagnosis::find($id);
    }

    public function create(array $data): Diagnosis
    {
        return Diagnosis::create($data);
    }

    public function update(Diagnosis $diagnosis, array $data): Diagnosis
    {
        $diagnosis->update($data);

        return $diagnosis;
    }

    public function delete(Diagnosis $diagnosis): bool
    {
        return (bool) $diagnosis->delete();
    }
}
