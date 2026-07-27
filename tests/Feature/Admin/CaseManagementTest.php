<?php

namespace Tests\Feature\Admin;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(UserRole::Admin)->create();
    }

    private function instructor(): User
    {
        return User::factory()->withRole(UserRole::Instructor)->create();
    }

    public function test_student_cannot_access_the_cases_index(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/admin/cases')->assertForbidden();
    }

    public function test_admin_can_view_the_cases_index(): void
    {
        CaseModel::factory()->create(['title' => 'Login Failure']);

        $response = $this->actingAs($this->admin())->get('/admin/cases');

        $response->assertOk();
        $response->assertSee('Login Failure');
        $response->assertSee('Add Case');
    }

    public function test_instructor_can_view_but_not_create_cases(): void
    {
        CaseModel::factory()->create(['title' => 'Login Failure']);

        $response = $this->actingAs($this->instructor())->get('/admin/cases');

        $response->assertOk();
        $response->assertSee('Login Failure');
        $response->assertDontSee('Add Case');
    }

    public function test_admin_can_view_the_create_case_form(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/cases/create');

        $response->assertOk();
        $response->assertSee('Add Case');
    }

    public function test_instructor_cannot_view_the_create_case_form(): void
    {
        $this->actingAs($this->instructor())->get('/admin/cases/create')->assertForbidden();
    }

    private function validCasePayload(array $overrides = []): array
    {
        $category = Category::factory()->create();

        return array_merge([
            'category_id' => $category->id,
            'title' => 'Login Failure Investigation',
            'slug' => 'login-failure-investigation',
            'summary' => 'A short teaser.',
            'ticket_content' => 'Users report they cannot log in since this morning.',
            'learning_outcomes' => 'Identify session/cookie misconfiguration.',
            'difficulty' => 'easy',
            'estimated_minutes' => 30,
            'model_solution_summary' => 'The session cookie domain was misconfigured.',
            'allow_reattempt' => '1',
        ], $overrides);
    }

    public function test_admin_can_create_a_case(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload());

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('cases', [
            'slug' => 'login-failure-investigation',
            'status' => CaseStatus::Draft->value,
            'version' => 1,
        ]);
    }

    public function test_creating_a_case_sets_the_authenticated_user_as_author(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/cases', $this->validCasePayload());

        $this->assertDatabaseHas('cases', ['created_by' => $admin->id]);
    }

    public function test_instructor_cannot_create_a_case(): void
    {
        $response = $this->actingAs($this->instructor())->post('/admin/cases', $this->validCasePayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('cases', ['slug' => 'login-failure-investigation']);
    }

    public function test_creating_a_case_requires_a_unique_slug(): void
    {
        CaseModel::factory()->create(['slug' => 'login-failure-investigation']);

        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload());

        $response->assertSessionHasErrors('slug');
        $this->assertSame(1, CaseModel::where('slug', 'login-failure-investigation')->count());
    }

    public function test_admin_can_update_a_case(): void
    {
        $case = CaseModel::factory()->create(['title' => 'Old Title']);

        $response = $this->actingAs($this->admin())->put(
            "/admin/cases/{$case->id}",
            $this->validCasePayload(['slug' => $case->slug, 'title' => 'New Title'])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'title' => 'New Title']);
    }

    public function test_updating_a_draft_case_does_not_bump_its_version(): void
    {
        $case = CaseModel::factory()->create(['version' => 1]);

        $this->actingAs($this->admin())->put(
            "/admin/cases/{$case->id}",
            $this->validCasePayload(['slug' => $case->slug])
        );

        $this->assertSame(1, $case->fresh()->version);
    }

    public function test_updating_a_published_case_bumps_its_version(): void
    {
        $case = CaseModel::factory()->published()->create(['version' => 1]);

        $this->actingAs($this->admin())->put(
            "/admin/cases/{$case->id}",
            $this->validCasePayload(['slug' => $case->slug])
        );

        $this->assertSame(2, $case->fresh()->version);
    }

    public function test_instructor_cannot_update_a_case(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->instructor())->put(
            "/admin/cases/{$case->id}",
            $this->validCasePayload(['slug' => $case->slug])
        );

        $response->assertForbidden();
    }

    public function test_admin_can_archive_a_case(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->delete("/admin/cases/{$case->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('cases', ['id' => $case->id]);
    }

    public function test_instructor_cannot_archive_a_case(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->instructor())->delete("/admin/cases/{$case->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('cases', ['id' => $case->id, 'deleted_at' => null]);
    }
}
