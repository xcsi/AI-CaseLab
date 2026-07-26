<?php

namespace App\Repositories\Contracts;

use App\Models\EvidenceItem;

interface EvidenceItemRepositoryInterface
{
    public function find(int $id): ?EvidenceItem;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EvidenceItem;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EvidenceItem $evidenceItem, array $data): EvidenceItem;

    public function delete(EvidenceItem $evidenceItem): bool;
}
