<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Enums\CaseDifficulty;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignedIncidentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_catalog(): void
    {
        CaseModel::factory()->published()->create(['title' => 'Login Failure']);

        $response = $this->get('/incidents');

        $response->assertOk();
        $response->assertSee('Login Failure');
        $response->assertDontSee('All Statuses');
    }

    public function test_draft_cases_never_appear_in_the_catalog(): void
    {
        CaseModel::factory()->create(['title' => 'Secret Draft Case']);

        $response = $this->get('/incidents');

        $response->assertOk();
        $response->assertDontSee('Secret Draft Case');
    }

    public function test_zero_published_cases_shows_the_triage_empty_state(): void
    {
        CaseModel::factory()->create();

        $response = $this->get('/incidents');

        $response->assertOk();
        $response->assertSee('New incidents are being triaged');
    }

    public function test_no_matches_shows_the_clear_filters_empty_state(): void
    {
        CaseModel::factory()->published()->create(['title' => 'Login Failure']);

        $response = $this->get('/incidents?search=nonexistent-term');

        $response->assertOk();
        $response->assertSee('No incidents match these filters');
        $response->assertSee('Clear Filters');
    }

    public function test_filters_by_category(): void
    {
        $backend = Category::factory()->create();
        $frontend = Category::factory()->create();
        CaseModel::factory()->published()->create(['category_id' => $backend->id, 'title' => 'Backend Case']);
        CaseModel::factory()->published()->create(['category_id' => $frontend->id, 'title' => 'Frontend Case']);

        $response = $this->get("/incidents?category={$backend->id}");

        $response->assertOk();
        $response->assertSee('Backend Case');
        $response->assertDontSee('Frontend Case');
    }

    public function test_filters_by_a_single_difficulty(): void
    {
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Easy->value, 'title' => 'Easy Case']);
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Hard->value, 'title' => 'Hard Case']);

        $response = $this->get('/incidents?difficulty[]=easy');

        $response->assertOk();
        $response->assertSee('Easy Case');
        $response->assertDontSee('Hard Case');
    }

    public function test_filters_by_multiple_difficulties_at_once(): void
    {
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Easy->value, 'title' => 'Easy Case']);
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Medium->value, 'title' => 'Medium Case']);
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Hard->value, 'title' => 'Hard Case']);

        $response = $this->get('/incidents?difficulty[]=easy&difficulty[]=hard');

        $response->assertOk();
        $response->assertSee('Easy Case');
        $response->assertSee('Hard Case');
        $response->assertDontSee('Medium Case');
    }

    public function test_searches_by_title_and_summary(): void
    {
        CaseModel::factory()->published()->create(['title' => 'API Returning 500', 'summary' => 'Checkout endpoint fails.']);
        CaseModel::factory()->published()->create(['title' => 'Login Failure', 'summary' => 'Users cannot authenticate.']);

        $response = $this->get('/incidents?search=checkout');

        $response->assertOk();
        $response->assertSee('API Returning 500');
        $response->assertDontSee('Login Failure');
    }

    public function test_filters_by_student_progress_status(): void
    {
        $student = User::factory()->create();
        $notStarted = CaseModel::factory()->published()->create(['title' => 'Not Started Case']);
        $inProgressCase = CaseModel::factory()->published()->create(['title' => 'In Progress Case']);
        $completedCase = CaseModel::factory()->published()->create(['title' => 'Completed Case']);

        CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $inProgressCase->id, 'status' => AttemptStatus::InProgress]);
        CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $completedCase->id, 'status' => AttemptStatus::Completed, 'completed_at' => now(), 'score_earned' => 80, 'max_possible_score' => 100]);

        $response = $this->actingAs($student)->get('/incidents?status=in_progress');
        $response->assertSee('In Progress Case');
        $response->assertDontSee('Not Started Case');
        $response->assertDontSee('Completed Case');

        $response = $this->actingAs($student)->get('/incidents?status=completed');
        $response->assertSee('Completed Case');
        $response->assertSee('80%');
        $response->assertDontSee('Not Started Case');

        $response = $this->actingAs($student)->get('/incidents?status=not_started');
        $response->assertSee('Not Started Case');
        $response->assertDontSee('In Progress Case');
        $response->assertDontSee('Completed Case');
    }

    public function test_sorts_by_title_ascending(): void
    {
        CaseModel::factory()->published()->create(['title' => 'Zeta Case']);
        CaseModel::factory()->published()->create(['title' => 'Alpha Case']);

        $response = $this->get('/incidents?sort=title');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zeta Case'), strpos($content, 'Alpha Case'));
    }

    public function test_sorts_by_difficulty_easy_to_hard(): void
    {
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Hard->value, 'title' => 'The Hard One']);
        CaseModel::factory()->published()->create(['difficulty' => CaseDifficulty::Easy->value, 'title' => 'The Easy One']);

        $response = $this->get('/incidents?sort=difficulty');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'The Hard One'), strpos($content, 'The Easy One'));
    }

    public function test_defaults_to_newest_first(): void
    {
        $older = CaseModel::factory()->published()->create(['title' => 'Older Case', 'created_at' => now()->subDays(2)]);
        $newer = CaseModel::factory()->published()->create(['title' => 'Newer Case', 'created_at' => now()]);

        $response = $this->get('/incidents');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Older Case'), strpos($content, 'Newer Case'));
    }

    public function test_paginates_at_nine_per_page(): void
    {
        CaseModel::factory()->published()->count(11)->create();

        $response = $this->get('/incidents');

        $response->assertOk();
        $this->assertSame(9, $response->viewData('cases')->count());
        $response->assertSee('Next');
    }

    public function test_a_card_links_to_the_incident_briefing_route(): void
    {
        $case = CaseModel::factory()->published()->create(['title' => 'Login Failure']);

        $response = $this->get('/incidents');

        $response->assertOk();
        $response->assertSee(route('cases.show', $case), false);
    }

    public function test_incident_briefing_placeholder_route_resolves_by_slug(): void
    {
        $case = CaseModel::factory()->published()->create(['slug' => 'login-failure']);

        $this->get('/incidents/login-failure')->assertOk();
    }
}
