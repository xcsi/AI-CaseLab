<?php

namespace Tests\Feature\Admin;

use App\Enums\AttemptStatus;
use App\Enums\UserRole;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_the_analytics_dashboard(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect('/login');
    }

    public function test_student_cannot_view_the_analytics_dashboard(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('admin.analytics.index'))->assertForbidden();
    }

    public function test_admin_can_view_the_analytics_dashboard(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSeeText('Completion Rate');
        $response->assertSeeText('Score Distribution');
        $response->assertSeeText('Hint Usage');
        $response->assertSeeText('Completion Time & Re-attempts');
        $response->assertSeeText('Category Breakdown');
    }

    public function test_instructor_can_view_the_analytics_dashboard(): void
    {
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $response = $this->actingAs($instructor)->get(route('admin.analytics.index'));

        $response->assertOk();
    }

    public function test_the_dashboard_renders_real_completion_and_category_numbers(): void
    {
        $category = Category::factory()->create(['name' => 'Networking']);
        $case = CaseModel::factory()->create(['category_id' => $category->id]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::Completed]);
        CaseAttempt::factory()->create(['case_id' => $case->id, 'status' => AttemptStatus::InProgress]);
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSeeText('Networking');
        $response->assertSeeText('1 of 2 attempts completed', false);
    }

    public function test_the_dashboard_handles_no_data_without_errors(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertSeeText('No attempts recorded yet.');
        $response->assertSeeText('No evaluated attempts yet.');
    }
}
