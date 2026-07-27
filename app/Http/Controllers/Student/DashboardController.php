<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Enums\CaseDifficulty;
use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        if (CaseAttempt::where('user_id', $user->id)->doesntExist()) {
            return view('dashboard', ['hasAnyActivity' => false]);
        }

        $closedCount = CaseAttempt::where('user_id', $user->id)
            ->where('status', AttemptStatus::Completed)
            ->count();

        return view('dashboard', [
            'hasAnyActivity' => true,
            'closedCount' => $closedCount,
            'averageScore' => $closedCount > 0 ? $this->averageScore($user->id) : null,
            'streak' => $this->currentStreak($user->id),
            'continuing' => CaseAttempt::with('case')
                ->where('user_id', $user->id)
                ->where('status', AttemptStatus::InProgress)
                ->latest('updated_at')
                ->first(),
            'recentActivity' => CaseAttempt::with('case')
                ->where('user_id', $user->id)
                ->whereIn('status', [AttemptStatus::InProgress, AttemptStatus::Submitted, AttemptStatus::Completed])
                ->latest('updated_at')
                ->take(5)
                ->get(),
            'recommended' => $this->recommendedCase($user->id),
        ]);
    }

    private function averageScore(int $userId): int
    {
        $completed = CaseAttempt::where('user_id', $userId)
            ->where('status', AttemptStatus::Completed)
            ->get();

        return (int) round($completed->avg(
            fn (CaseAttempt $attempt) => $attempt->max_possible_score > 0
                ? ($attempt->score_earned / $attempt->max_possible_score) * 100
                : 0
        ));
    }

    /**
     * Consecutive-day streak of investigation activity, counting backward
     * from today (or yesterday, if nothing has happened yet today) until
     * the chain of active days breaks.
     */
    private function currentStreak(int $userId): int
    {
        $activeDates = CaseAttempt::where('user_id', $userId)
            ->get(['started_at'])
            ->map(fn (CaseAttempt $attempt) => $attempt->started_at->toDateString())
            ->unique();

        $cursor = today();

        if (! $activeDates->contains($cursor->toDateString())) {
            $cursor = $cursor->copy()->subDay();
        }

        $streak = 0;

        while ($activeDates->contains($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return $streak;
    }

    /**
     * Rule-based recommendation (not AI): next difficulty up in the same
     * category as the student's last completed case, falling back to the
     * same category/difficulty, then to the easiest unattempted published
     * case platform-wide.
     */
    private function recommendedCase(int $userId): ?CaseModel
    {
        $attemptedCaseIds = CaseAttempt::where('user_id', $userId)->pluck('case_id');

        $lastCompleted = CaseAttempt::with('case')
            ->where('user_id', $userId)
            ->where('status', AttemptStatus::Completed)
            ->latest('completed_at')
            ->first();

        if ($lastCompleted?->case) {
            $candidate = $this->nextCaseInCategory($lastCompleted->case, $attemptedCaseIds);

            if ($candidate) {
                return $candidate;
            }
        }

        foreach (CaseDifficulty::cases() as $difficulty) {
            $candidate = CaseModel::published()
                ->where('difficulty', $difficulty)
                ->whereNotIn('id', $attemptedCaseIds)
                ->first();

            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, int>  $attemptedCaseIds
     */
    private function nextCaseInCategory(CaseModel $lastCase, Collection $attemptedCaseIds): ?CaseModel
    {
        $difficulties = CaseDifficulty::cases();
        $currentIndex = array_search($lastCase->difficulty, $difficulties, true);
        $nextDifficulty = $difficulties[$currentIndex + 1] ?? $lastCase->difficulty;

        return CaseModel::published()
            ->where('category_id', $lastCase->category_id)
            ->where('difficulty', $nextDifficulty)
            ->whereNotIn('id', $attemptedCaseIds)
            ->first()
            ?? CaseModel::published()
                ->where('category_id', $lastCase->category_id)
                ->whereNotIn('id', $attemptedCaseIds)
                ->first();
    }
}
