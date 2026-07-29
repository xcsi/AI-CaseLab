<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\Diagnosis;
use App\Models\Hint;
use App\Models\HintUnlock;
use App\Models\RubricCriterion;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function evaluatedAttempt(CaseModel $case, array $attemptAttributes = []): CaseAttempt
    {
        $attempt = CaseAttempt::factory()->create(array_merge([
            'case_id' => $case->id,
        ], $attemptAttributes));
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A timeout on the upstream service.',
        ]);

        app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        return $attempt->fresh();
    }

    // --- completionMetrics ------------------------------------------------

    public function test_completion_metrics_counts_attempts_by_status(): void
    {
        $case = CaseModel::factory()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::InProgress]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Submitted]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Abandoned]);

        $metrics = app(AnalyticsService::class)->completionMetrics([$case->id]);

        $this->assertSame(5, $metrics['started']);
        $this->assertSame(2, $metrics['completed']);
        $this->assertSame(40.0, $metrics['completion_rate_percent']);
        $this->assertSame([
            'in_progress' => 1,
            'submitted' => 1,
            'completed' => 2,
            'abandoned' => 1,
        ], $metrics['by_status']);
    }

    public function test_completion_metrics_is_null_when_there_are_no_attempts(): void
    {
        $case = CaseModel::factory()->create();

        $metrics = app(AnalyticsService::class)->completionMetrics([$case->id]);

        $this->assertSame(0, $metrics['started']);
        $this->assertNull($metrics['completion_rate_percent']);
    }

    public function test_completion_metrics_scoping_excludes_attempts_from_other_cases(): void
    {
        $case = CaseModel::factory()->create();
        $otherCase = CaseModel::factory()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);
        CaseAttempt::factory()->create(['case_id' => $otherCase->id, 'status' => AttemptStatus::Completed]);

        $metrics = app(AnalyticsService::class)->completionMetrics([$case->id]);

        $this->assertSame(1, $metrics['started']);
    }

    // --- scoreDistribution --------------------------------------------------

    public function test_score_distribution_buckets_evaluations_by_percentage(): void
    {
        $case = CaseModel::factory()->create(['max_score' => 100]);
        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'weight' => 100,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout']],
        ]);
        // Full match -> 100%.
        $this->evaluatedAttempt($case, ['max_possible_score' => 100]);
        // No match -> 0%.
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id, 'max_possible_score' => 100]);
        $diagnosis = Diagnosis::factory()->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'A database connection was refused.',
        ]);
        app(EvaluationService::class)->evaluate($attempt, $diagnosis);

        $distribution = app(AnalyticsService::class)->scoreDistribution([$case->id]);

        $this->assertSame(2, $distribution['evaluated_count']);
        $this->assertSame(50.0, $distribution['average_percent']);
        $this->assertSame(1, $distribution['buckets']['below_50']);
        $this->assertSame(0, $distribution['buckets']['between_50_and_75']);
        $this->assertSame(1, $distribution['buckets']['above_75']);
    }

    public function test_score_distribution_is_null_when_there_are_no_evaluations(): void
    {
        $case = CaseModel::factory()->create();

        $distribution = app(AnalyticsService::class)->scoreDistribution([$case->id]);

        $this->assertSame(0, $distribution['evaluated_count']);
        $this->assertNull($distribution['average_percent']);
    }

    // --- hintUsage ------------------------------------------------------------

    public function test_hint_usage_totals_unlocks_and_averages_per_completed_attempt(): void
    {
        $case = CaseModel::factory()->create();
        // hint_unlocks has a unique (case_attempt_id, hint_id) constraint — a
        // given attempt can't unlock the same hint twice — so repeat unlocks
        // for one attempt require a second hint, not a duplicate row.
        $hint1 = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);
        $hint2 = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 3]);
        $attempt1 = CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);
        $attempt2 = CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);
        HintUnlock::create(['case_attempt_id' => $attempt1->id, 'hint_id' => $hint1->id, 'penalty_applied' => 5, 'unlocked_at' => now()]);
        HintUnlock::create(['case_attempt_id' => $attempt2->id, 'hint_id' => $hint1->id, 'penalty_applied' => 5, 'unlocked_at' => now()]);
        HintUnlock::create(['case_attempt_id' => $attempt2->id, 'hint_id' => $hint2->id, 'penalty_applied' => 3, 'unlocked_at' => now()]);

        $usage = app(AnalyticsService::class)->hintUsage([$case->id]);

        $this->assertSame(3, $usage['total_unlocks']);
        $this->assertSame(1.5, $usage['average_hints_per_completed_attempt']);
        $this->assertSame(2, count($usage['by_hint']));
        $byHintId = collect($usage['by_hint'])->keyBy('hint_id');
        $this->assertSame(2, $byHintId[$hint1->id]['unlock_count']);
        $this->assertSame(10.0, $byHintId[$hint1->id]['total_penalty_applied']);
        $this->assertSame(1, $byHintId[$hint2->id]['unlock_count']);
        $this->assertSame(3.0, $byHintId[$hint2->id]['total_penalty_applied']);
    }

    public function test_hint_usage_scoping_excludes_hints_from_other_cases(): void
    {
        $case = CaseModel::factory()->create();
        $otherCase = CaseModel::factory()->create();
        $hint = Hint::factory()->create(['case_id' => $case->id]);
        $otherHint = Hint::factory()->create(['case_id' => $otherCase->id]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $otherAttempt = CaseAttempt::factory()->create(['case_id' => $otherCase->id]);
        HintUnlock::create(['case_attempt_id' => $attempt->id, 'hint_id' => $hint->id, 'penalty_applied' => 2, 'unlocked_at' => now()]);
        HintUnlock::create(['case_attempt_id' => $otherAttempt->id, 'hint_id' => $otherHint->id, 'penalty_applied' => 2, 'unlocked_at' => now()]);

        $usage = app(AnalyticsService::class)->hintUsage([$case->id]);

        $this->assertSame(1, $usage['total_unlocks']);
    }

    // --- averageCompletionTime -------------------------------------------------

    public function test_average_completion_time_averages_seconds_between_start_and_completion(): void
    {
        $case = CaseModel::factory()->create();
        CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'status' => AttemptStatus::Completed,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
        CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'status' => AttemptStatus::Completed,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ]);

        $time = app(AnalyticsService::class)->averageCompletionTime([$case->id]);

        $this->assertSame(900.0, $time['average_seconds']);
        $this->assertSame(15.0, $time['average_minutes']);
    }

    public function test_average_completion_time_is_null_when_nothing_is_completed(): void
    {
        $case = CaseModel::factory()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::InProgress]);

        $time = app(AnalyticsService::class)->averageCompletionTime([$case->id]);

        $this->assertNull($time['average_seconds']);
        $this->assertNull($time['average_minutes']);
    }

    // --- reattemptStatistics ----------------------------------------------------

    public function test_reattempt_statistics_counts_students_who_attempted_more_than_once(): void
    {
        $case = CaseModel::factory()->create();
        $studentA = User::factory()->create();
        $studentB = User::factory()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $studentA->id]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $studentA->id]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $studentB->id]);

        $stats = app(AnalyticsService::class)->reattemptStatistics([$case->id]);

        $this->assertSame(2, $stats['students_with_any_attempt']);
        $this->assertSame(1, $stats['students_with_multiple_attempts']);
        $this->assertSame(50.0, $stats['reattempt_rate_percent']);
    }

    public function test_reattempt_statistics_is_null_when_there_are_no_attempts(): void
    {
        $case = CaseModel::factory()->create();

        $stats = app(AnalyticsService::class)->reattemptStatistics([$case->id]);

        $this->assertSame(0, $stats['students_with_any_attempt']);
        $this->assertNull($stats['reattempt_rate_percent']);
    }

    // --- summary ------------------------------------------------------------------

    public function test_summary_composes_every_metric_for_the_given_scope(): void
    {
        $case = CaseModel::factory()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);

        $summary = app(AnalyticsService::class)->summary([$case->id]);

        $this->assertArrayHasKey('completion', $summary);
        $this->assertArrayHasKey('score_distribution', $summary);
        $this->assertArrayHasKey('hint_usage', $summary);
        $this->assertArrayHasKey('completion_time', $summary);
        $this->assertArrayHasKey('reattempts', $summary);
        $this->assertSame(1, $summary['completion']['started']);
    }

    public function test_summary_with_no_scope_is_platform_wide(): void
    {
        CaseModel::factory()->create();
        $case = CaseModel::factory()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id]);

        $summary = app(AnalyticsService::class)->summary();

        $this->assertSame(1, $summary['completion']['started']);
    }

    // --- categoryAggregates -----------------------------------------------------

    public function test_category_aggregates_rolls_up_metrics_per_category_with_isolation(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();
        $caseA1 = CaseModel::factory()->create(['category_id' => $categoryA->id]);
        $caseA2 = CaseModel::factory()->create(['category_id' => $categoryA->id]);
        $caseB1 = CaseModel::factory()->create(['category_id' => $categoryB->id]);

        CaseAttempt::factory()->create(['case_id' => $caseA1->id, 'status' => AttemptStatus::Completed]);
        CaseAttempt::factory()->create(['case_id' => $caseA2->id, 'status' => AttemptStatus::Completed]);
        CaseAttempt::factory()->create(['case_id' => $caseB1->id, 'status' => AttemptStatus::Completed]);

        $aggregates = app(AnalyticsService::class)->categoryAggregates();

        $rowA = $aggregates->firstWhere('category_id', $categoryA->id);
        $rowB = $aggregates->firstWhere('category_id', $categoryB->id);

        $this->assertSame(2, $rowA['case_count']);
        $this->assertSame(2, $rowA['completion']['started']);
        $this->assertSame(1, $rowB['case_count']);
        $this->assertSame(1, $rowB['completion']['started']);
    }

    public function test_category_aggregates_handles_a_category_with_no_cases(): void
    {
        $category = Category::factory()->create();

        $aggregates = app(AnalyticsService::class)->categoryAggregates();

        $row = $aggregates->firstWhere('category_id', $category->id);

        $this->assertSame(0, $row['case_count']);
        $this->assertSame(0, $row['completion']['started']);
        $this->assertNull($row['completion']['completion_rate_percent']);
    }
}
