<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringOfficeShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_sample_incident_link_and_auth_links_only(): void
    {
        $response = $this->get('/incidents');

        $response->assertOk();
        $response->assertSee('View a Sample Incident');
        $response->assertSee('Login');
        $response->assertSee('Register');
        $response->assertDontSee('Inbox');
        $response->assertDontSee('Work History');
    }

    public function test_authenticated_student_sees_the_full_shell_nav(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Inbox');
        $response->assertSee('Assigned Incidents');
        $response->assertSee('Work History');
        $response->assertSee($student->name);
        $response->assertDontSee('View a Sample Incident');
    }

    public function test_a_plain_student_does_not_see_an_admin_console_link(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Admin Console');
    }

    public function test_an_admin_sees_an_admin_console_link_on_the_shell(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Admin Console');
        $response->assertSee(route('admin.dashboard'), false);
    }

    public function test_an_instructor_sees_an_admin_console_link_on_the_shell(): void
    {
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $response = $this->actingAs($instructor)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Admin Console');
    }

    public function test_the_incidents_route_replaces_the_old_cases_placeholder_url(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/incidents')->assertOk();
        $this->actingAs($student)->get('/cases')->assertNotFound();
    }

    public function test_the_work_history_route_replaces_the_old_progress_placeholder_url(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/work-history')->assertOk();
        $this->actingAs($student)->get('/progress')->assertNotFound();
    }

    public function test_work_history_requires_authentication(): void
    {
        $this->get('/work-history')->assertRedirect('/login');
    }

    public function test_assigned_incidents_link_is_marked_active_on_the_incidents_page(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/incidents');

        $response->assertOk();
        $response->assertSee('nav-link active', false);
    }

    public function test_placeholder_pages_show_workplace_titles(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/incidents')->assertSee('Assigned Incidents');
        $this->actingAs($student)->get('/work-history')->assertSee('Work History');
    }
}
