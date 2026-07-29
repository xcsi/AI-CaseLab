<?php

namespace App\Repositories\Eloquent;

use App\Enums\AttemptStatus;
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

    public function statusCounts(?array $caseIds = null): array
    {
        $query = CaseAttempt::query();

        if ($caseIds !== null) {
            $query->whereIn('case_id', $caseIds);
        }

        $counts = $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'in_progress' => (int) ($counts[AttemptStatus::InProgress->value] ?? 0),
            'submitted' => (int) ($counts[AttemptStatus::Submitted->value] ?? 0),
            'completed' => (int) ($counts[AttemptStatus::Completed->value] ?? 0),
            'abandoned' => (int) ($counts[AttemptStatus::Abandoned->value] ?? 0),
        ];
    }

    public function averageCompletionSeconds(?array $caseIds = null): ?float
    {
        $query = CaseAttempt::query()
            ->where('status', AttemptStatus::Completed)
            ->whereNotNull('completed_at');

        if ($caseIds !== null) {
            $query->whereIn('case_id', $caseIds);
        }

        $durations = $query->get(['started_at', 'completed_at'])
            ->map(fn (CaseAttempt $attempt) => abs($attempt->completed_at->diffInSeconds($attempt->started_at)));

        return $durations->isNotEmpty() ? (float) $durations->avg() : null;
    }

    public function reattemptCounts(?array $caseIds = null): array
    {
        $query = CaseAttempt::query();

        if ($caseIds !== null) {
            $query->whereIn('case_id', $caseIds);
        }

        $studentCasePairs = $query->selectRaw('user_id, case_id, count(*) as attempts')
            ->groupBy('user_id', 'case_id')
            ->get();

        return [
            'students_with_any_attempt' => $studentCasePairs->count(),
            'students_with_multiple_attempts' => $studentCasePairs->filter(fn ($row) => $row->attempts > 1)->count(),
        ];
    }
}
