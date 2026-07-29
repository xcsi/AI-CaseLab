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

    /**
     * Attempt counts per status — the raw material for completion-rate
     * analytics. Null $caseIds means platform-wide.
     *
     * @param  array<int, int>|null  $caseIds
     * @return array{in_progress: int, submitted: int, completed: int, abandoned: int}
     */
    public function statusCounts(?array $caseIds = null): array;

    /**
     * Average seconds between started_at and completed_at across Completed
     * attempts. Computed in PHP rather than SQL (e.g. TIMESTAMPDIFF) so it
     * behaves identically on MySQL (dev/prod) and SQLite (tests).
     *
     * @param  array<int, int>|null  $caseIds
     */
    public function averageCompletionSeconds(?array $caseIds = null): ?float;

    /**
     * Counts of distinct (student, case) pairs — the denominator/numerator
     * for a reattempt rate: how many student-case combinations were
     * attempted at all, and how many of those were attempted more than once.
     *
     * @param  array<int, int>|null  $caseIds
     * @return array{students_with_any_attempt: int, students_with_multiple_attempts: int}
     */
    public function reattemptCounts(?array $caseIds = null): array;
}
