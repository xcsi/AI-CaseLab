<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(UserRole::Admin)->create();
    }

    public function test_student_cannot_access_the_dashboard(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/admin')->assertForbidden();
    }

    public function test_admin_sees_case_status_counts(): void
    {
        CaseModel::factory()->count(2)->create();
        CaseModel::factory()->published()->create();
        $archived = CaseModel::factory()->create();
        $archived->delete();

        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSeeText('Draft Cases');
        $response->assertSeeText('Published Cases');
        $response->assertSeeText('Archived Cases');
    }

    public function test_a_draft_case_missing_a_rubric_shows_up_in_needs_attention(): void
    {
        $case = CaseModel::factory()->create(['title' => 'Incomplete Draft']);

        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSee('Incomplete Draft');
        $response->assertSee('The case needs at least one rubric criterion.');
    }

    public function test_a_publishable_draft_case_does_not_show_up_in_needs_attention(): void
    {
        $case = CaseModel::factory()->create(['title' => 'Ready Draft']);
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);

        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Ready Draft');
    }

    public function test_published_cases_do_not_show_up_in_needs_attention(): void
    {
        CaseModel::factory()->published()->create(['title' => 'Live Case']);

        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertDontSee('Live Case');
    }

    public function test_creating_a_case_appears_in_recent_activity(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/cases', [
            'category_id' => Category::factory()->create()->id,
            'title' => 'Activity Feed Case',
            'slug' => 'activity-feed-case',
            'ticket_content' => 'Something broke.',
            'difficulty' => 'easy',
            'estimated_minutes' => 30,
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSeeText($admin->name.' created case "Activity Feed Case"', false);
    }

    public function test_publishing_a_case_appears_in_recent_activity(): void
    {
        $admin = $this->admin();
        $case = CaseModel::factory()->create(['title' => 'Publish Feed Case']);
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);

        $this->actingAs($admin)->post("/admin/cases/{$case->id}/publish");

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSeeText($admin->name.' published case "Publish Feed Case"', false);
    }

    public function test_archiving_a_case_appears_in_recent_activity(): void
    {
        $admin = $this->admin();
        $case = CaseModel::factory()->create(['title' => 'Archive Feed Case']);

        $this->actingAs($admin)->delete("/admin/cases/{$case->id}");

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSeeText($admin->name.' archived case "Archive Feed Case"', false);
    }

    public function test_admin_sees_category_statistics(): void
    {
        $category = Category::factory()->create(['name' => 'Backend']);
        CaseModel::factory()->create(['category_id' => $category->id]);
        CaseModel::factory()->published()->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSeeText('Backend');
    }

    public function test_quick_action_links_are_present(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin');

        $response->assertOk();
        $response->assertSee(route('admin.cases.create'), false);
        $response->assertSee(route('admin.categories.index'), false);
    }
}
