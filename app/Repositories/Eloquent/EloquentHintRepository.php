<?php

namespace App\Repositories\Eloquent;

use App\Models\Hint;
use App\Models\HintUnlock;
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

    public function usageCounts(?array $caseIds = null): array
    {
        $query = HintUnlock::query();

        if ($caseIds !== null) {
            $query->whereHas('hint', fn ($q) => $q->whereIn('case_id', $caseIds));
        }

        return $query->selectRaw('hint_id, count(*) as unlock_count, sum(penalty_applied) as total_penalty_applied')
            ->groupBy('hint_id')
            ->get()
            ->map(fn ($row) => [
                'hint_id' => (int) $row->hint_id,
                'unlock_count' => (int) $row->unlock_count,
                'total_penalty_applied' => (float) $row->total_penalty_applied,
            ])
            ->all();
    }
}
