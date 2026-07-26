<?php

namespace App\Repositories\Eloquent;

use App\Models\Hint;
use App\Repositories\Contracts\HintRepositoryInterface;

class EloquentHintRepository implements HintRepositoryInterface
{
    public function find(int $id): ?Hint
    {
        return Hint::find($id);
    }

    public function create(array $data): Hint
    {
        return Hint::create($data);
    }

    public function update(Hint $hint, array $data): Hint
    {
        $hint->update($data);

        return $hint;
    }

    public function delete(Hint $hint): bool
    {
        return (bool) $hint->delete();
    }
}
