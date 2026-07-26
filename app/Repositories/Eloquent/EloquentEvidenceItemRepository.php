<?php

namespace App\Repositories\Eloquent;

use App\Models\EvidenceItem;
use App\Repositories\Contracts\EvidenceItemRepositoryInterface;

class EloquentEvidenceItemRepository implements EvidenceItemRepositoryInterface
{
    public function find(int $id): ?EvidenceItem
    {
        return EvidenceItem::find($id);
    }

    public function create(array $data): EvidenceItem
    {
        return EvidenceItem::create($data);
    }

    public function update(EvidenceItem $evidenceItem, array $data): EvidenceItem
    {
        $evidenceItem->update($data);

        return $evidenceItem;
    }

    public function delete(EvidenceItem $evidenceItem): bool
    {
        return (bool) $evidenceItem->delete();
    }
}
