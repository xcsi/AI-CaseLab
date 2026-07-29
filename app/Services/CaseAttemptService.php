<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Exceptions\ReattemptNotAllowedException;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use App\Repositories\Contracts\CaseAttemptRepositoryInterface;

class CaseAttemptService
{
    public function __construct(
        private readonly CaseAttemptRepositoryInterface $caseAttempts,
    ) {}

    /**
     * Resumes the student's in-progress attempt for this case if one
     * exists; otherwise starts a new one, enforcing the case's reattempt
     * policy against any already-completed attempt.
     *
     * @throws ReattemptNotAllowedException
     */
    public function start(CaseModel $case, User $user): CaseAttempt
    {
        $inProgress = $case->attempts()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('updated_at')
            ->first();

        if ($inProgress) {
            return $inProgress;
        }

        $hasCompleted = $case->attempts()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::Completed)
            ->exists();

        if ($hasCompleted && ! $case->allow_reattempt) {
            throw new ReattemptNotAllowedException;
        }

        return $this->caseAttempts->create([
            'case_id' => $case->id,
            'user_id' => $user->id,
            'status' => AttemptStatus::InProgress,
            'case_version' => $case->version,
            'started_at' => now(),
            'max_possible_score' => $case->max_score,
        ]);
    }
}
