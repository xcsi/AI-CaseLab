<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\CaseDifficulty;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_student_with_no_attempts_sees_the_first_incident_empty_state(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Your first incident is waiting');
        $response->assertDontSee('Incidents Closed');
    }

    public function test_the_stat_row_is_hidden_when_nothing_has_been_closed_yet(): void
    {
        $student = User::factory()->create();
        CaseAttempt::factory()->create(['user_id' => $student->id, 'status' => AttemptStatus::InProgress]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Incidents Closed');
        $response->assertDontSee('Your first incident is waiting');
    }

    public function test_the_stat_row_shows_closed_count_and_average_score(): void
    {
        $student = User::factory()->create();
        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'status' => AttemptStatus::Completed,
            'score_earned' => 80,
            'max_possible_score' => 100,
            'completed_at' => now(),
        ]);
        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'status' => AttemptStatus::Completed,
            'score_earned' => 60,
            'max_possible_score' => 100,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSeeText('Incidents Closed');
        $response->assertSeeText('2');
        $response->assertSeeText('70%');
    }

    public function test_continue_investigation_card_shows_the_in_progress_attempt(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['title' => 'API Returning 500']);
        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $case->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Continue Investigation');
        $response->assertSee('API Returning 500');
        $response->assertSee('Resume');
    }

    public function test_no_continue_investigation_card_without_an_in_progress_attempt(): void
    {
        $student = User::factory()->create();
        CaseAttempt::factory()->create(['user_id' => $student->id, 'status' => AttemptStatus::Completed, 'completed_at' => now()]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Continue Investigation');
    }

    public function test_recent_activity_lists_completed_and_in_progress_attempts(): void
    {
        $student = User::factory()->create();
        $closedCase = CaseModel::factory()->create(['title' => 'Login Failure']);
        $openCase = CaseModel::factory()->create(['title' => 'DB Performance Issue']);

        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $closedCase->id,
            'status' => AttemptStatus::Completed,
            'score_earned' => 91,
            'max_possible_score' => 100,
            'completed_at' => now(),
        ]);
        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $openCase->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Login Failure');
        $response->assertSee('91%');
        $response->assertSee('DB Performance Issue');
        $response->assertSeeText('in progress');
    }

    public function test_recent_activity_shows_at_most_five_attempts(): void
    {
        $student = User::factory()->create();
        CaseAttempt::factory()->count(7)->create([
            'user_id' => $student->id,
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $this->assertSame(5, $response->viewData('recentActivity')->count());
    }

    public function test_recommended_next_prefers_the_next_difficulty_up_in_the_same_category(): void
    {
        $student = User::factory()->create();
        $category = Category::factory()->create();

        $completedCase = CaseModel::factory()->published()->create([
            'category_id' => $category->id,
            'difficulty' => CaseDifficulty::Easy->value,
        ]);
        $nextUp = CaseModel::factory()->published()->create([
            'category_id' => $category->id,
            'difficulty' => CaseDifficulty::Medium->value,
            'title' => 'Next Difficulty Case',
        ]);
        // A same-difficulty case in the same category that should be skipped in favor of $nextUp.
        CaseModel::factory()->published()->create([
            'category_id' => $category->id,
            'difficulty' => CaseDifficulty::Easy->value,
            'title' => 'Same Difficulty Case',
        ]);

        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $completedCase->id,
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Next Difficulty Case');
        $response->assertDontSee('Same Difficulty Case');
    }

    public function test_recommended_next_falls_back_to_the_same_category_when_no_next_difficulty_exists(): void
    {
        $student = User::factory()->create();
        $category = Category::factory()->create();

        $completedCase = CaseModel::factory()->published()->create([
            'category_id' => $category->id,
            'difficulty' => CaseDifficulty::Hard->value,
        ]);
        $sameCategory = CaseModel::factory()->published()->create([
            'category_id' => $category->id,
            'difficulty' => CaseDifficulty::Easy->value,
            'title' => 'Same Category Fallback',
        ]);

        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $completedCase->id,
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Same Category Fallback');
    }

    public function test_recommended_next_falls_back_to_the_easiest_unattempted_case_platform_wide(): void
    {
        $student = User::factory()->create();
        CaseAttempt::factory()->create(['user_id' => $student->id, 'status' => AttemptStatus::InProgress]);

        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Hard->value, 'title' => 'Hard Case']);
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Easy->value, 'title' => 'Easy Case']);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Easy Case');
    }

    public function test_no_recommendation_when_every_published_case_is_already_attempted(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create();
        CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id, 'status' => AttemptStatus::InProgress]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Recommended Next');
    }

    public function test_streak_counts_consecutive_active_days_ending_today(): void
    {
        $student = User::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-07-27 10:00:00'));

        CaseAttempt::factory()->create(['user_id' => $student->id, 'started_at' => Carbon::parse('2026-07-27 09:00:00'), 'status' => AttemptStatus::Completed, 'completed_at' => now()]);
        CaseAttempt::factory()->create(['user_id' => $student->id, 'started_at' => Carbon::parse('2026-07-26 09:00:00'), 'status' => AttemptStatus::Completed, 'completed_at' => Carbon::parse('2026-07-26 10:00:00')]);
        CaseAttempt::factory()->create(['user_id' => $student->id, 'started_at' => Carbon::parse('2026-07-24 09:00:00'), 'status' => AttemptStatus::Completed, 'completed_at' => Carbon::parse('2026-07-24 10:00:00')]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $this->assertSame(2, $response->viewData('streak'));
    }

    public function test_streak_still_counts_when_todays_activity_has_not_happened_yet(): void
    {
        $student = User::factory()->create();
        Carbon::setTestNow(Carbon::parse('2026-07-27 08:00:00'));

        CaseAttempt::factory()->create(['user_id' => $student->id, 'started_at' => Carbon::parse('2026-07-26 09:00:00'), 'status' => AttemptStatus::Completed, 'completed_at' => Carbon::parse('2026-07-26 10:00:00')]);

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $this->assertSame(1, $response->viewData('streak'));
    }
}
