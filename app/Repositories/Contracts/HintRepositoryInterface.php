<?php

namespace App\Repositories\Contracts;

use App\Models\Hint;

interface HintRepositoryInterface
{
    public function find(int $id): ?Hint;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Hint;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Hint $hint, array $data): Hint;

    public function delete(Hint $hint): bool;

    /**
     * Unlock counts and total penalty applied per hint, from hint_unlocks —
     * the raw material for hint-usage analytics. Null $caseIds means
     * platform-wide.
     *
     * @param  array<int, int>|null  $caseIds
     * @return array<int, array{hint_id: int, unlock_count: int, total_penalty_applied: float}>
     */
    public function usageCounts(?array $caseIds = null): array;
}
