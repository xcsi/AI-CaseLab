<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Contracts\CaseAttemptRepositoryInterface;
use App\Repositories\Contracts\EvaluationRepositoryInterface;
use App\Repositories\Contracts\HintRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * The reusable analytics layer future dashboards/reports will consume.
 * Every method here reads existing case_attempts/evaluations/hint_unlocks
 * data through the repositories — no new scoring or grading logic, only
 * aggregation of numbers those two milestones already produced.
 *
 * Every method accepts an optional array of case IDs to scope the result:
 * null means platform-wide, a single-element array scopes to one case, and
 * a multi-case array (e.g. every case in a category) scopes to that set —
 * one parameter shape covers all three, so category-level aggregates reuse
 * the exact same methods as single-case/platform-wide ones instead of a
 * separate implementation.
 */
class AnalyticsService
{
    public function __construct(
        private readonly CaseAttemptRepositoryInterface $caseAttempts,
        private readonly EvaluationRepositoryInterface $evaluations,
        private readonly HintRepositoryInterface $hints,
    ) {}

    /**
     * @param  array<int, int>|null  $caseIds
     * @return array{started: int, completed: int, completion_rate_percent: ?float, by_status: array{in_progress: int, submitted: int, completed: int, abandoned: int}}
     */
    public function completionMetrics(?array $caseIds = null): array
    {
        $byStatus = $this->caseAttempts->statusCounts($caseIds);
        $started = array_sum($byStatus);
        $completed = $byStatus['completed'];

        return [
            'started' => $started,
            'completed' => $completed,
            'completion_rate_percent' => $started > 0 ? round($completed / $started * 100, 1) : null,
            'by_status' => $byStatus,
        ];
    }

    /**
     * @param  array<int, int>|null  $caseIds
     * @return array{evaluated_count: int, average_percent: ?float, buckets: array{below_50: int, between_50_and_75: int, above_75: int}}
     */
    public function scoreDistribution(?array $caseIds = null): array
    {
        $percentages = $this->evaluations->scorePercentages($caseIds);
        $count = count($percentages);

        $buckets = ['below_50' => 0, 'between_50_and_75' => 0, 'above_75' => 0];

        foreach ($percentages as $percent) {
            $buckets[match (true) {
                $percent < 50 => 'below_50',
                $percent < 75 => 'between_50_and_75',
                default => 'above_75',
            }]++;
        }

        return [
            'evaluated_count' => $count,
            'average_percent' => $count > 0 ? round(array_sum($percentages) / $count, 1) : null,
            'buckets' => $buckets,
        ];
    }

    /**
     * @param  array<int, int>|null  $caseIds
     * @return array{total_unlocks: int, average_hints_per_completed_attempt: ?float, by_hint: array<int, array{hint_id: int, unlock_count: int, total_penalty_applied: float}>}
     */
    public function hintUsage(?array $caseIds = null): array
    {
        $byHint = $this->hints->usageCounts($caseIds);
        $totalUnlocks = array_sum(array_column($byHint, 'unlock_count'));
        $completedAttempts = $this->caseAttempts->statusCounts($caseIds)['completed'];

        return [
            'total_unlocks' => $totalUnlocks,
            'average_hints_per_completed_attempt' => $completedAttempts > 0
                ? round($totalUnlocks / $completedAttempts, 2)
                : null,
            'by_hint' => $byHint,
        ];
    }

    /**
     * @param  array<int, int>|null  $caseIds
     * @return array{average_seconds: ?float, average_minutes: ?float}
     */
    public function averageCompletionTime(?array $caseIds = null): array
    {
        $seconds = $this->caseAttempts->averageCompletionSeconds($caseIds);

        return [
            'average_seconds' => $seconds,
            'average_minutes' => $seconds !== null ? round($seconds / 60, 1) : null,
        ];
    }

    /**
     * @param  array<int, int>|null  $caseIds
     * @return array{students_with_any_attempt: int, students_with_multiple_attempts: int, reattempt_rate_percent: ?float}
     */
    public function reattemptStatistics(?array $caseIds = null): array
    {
        $counts = $this->caseAttempts->reattemptCounts($caseIds);
        $withAny = $counts['students_with_any_attempt'];
        $withMultiple = $counts['students_with_multiple_attempts'];

        return [
            'students_with_any_attempt' => $withAny,
            'students_with_multiple_attempts' => $withMultiple,
            'reattempt_rate_percent' => $withAny > 0 ? round($withMultiple / $withAny * 100, 1) : null,
        ];
    }

    /**
     * Every metric above, bundled for one scope — the single call site a
     * future dashboard/report needs instead of five.
     *
     * @param  array<int, int>|null  $caseIds
     * @return array{completion: array<string, mixed>, score_distribution: array<string, mixed>, hint_usage: array<string, mixed>, completion_time: array<string, mixed>, reattempts: array<string, mixed>}
     */
    public function summary(?array $caseIds = null): array
    {
        return [
            'completion' => $this->completionMetrics($caseIds),
            'score_distribution' => $this->scoreDistribution($caseIds),
            'hint_usage' => $this->hintUsage($caseIds),
            'completion_time' => $this->averageCompletionTime($caseIds),
            'reattempts' => $this->reattemptStatistics($caseIds),
        ];
    }

    /**
     * summary(), rolled up per category — every category's numbers are
     * produced by the exact same methods as the single-case/platform-wide
     * callers, scoped to that category's case IDs.
     *
     * @return Collection<int, array{category_id: int, category_name: string, case_count: int, completion: array<string, mixed>, score_distribution: array<string, mixed>, hint_usage: array<string, mixed>, completion_time: array<string, mixed>, reattempts: array<string, mixed>}>
     */
    public function categoryAggregates(): Collection
    {
        return Category::query()->orderBy('name')->get()->map(function (Category $category) {
            $caseIds = $category->cases()->pluck('id')->all();

            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'case_count' => count($caseIds),
                ...$this->summary($caseIds),
            ];
        });
    }
}
