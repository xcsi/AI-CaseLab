<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_a_student_workspace_link_on_the_admin_sidebar(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Student Workspace');
        $response->assertSee(route('dashboard'), false);
    }

    public function test_an_instructor_sees_a_student_workspace_link_on_the_admin_sidebar(): void
    {
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $response = $this->actingAs($instructor)->get('/admin');

        $response->assertOk();
        $response->assertSee('Student Workspace');
    }

    public function test_a_student_cannot_reach_the_admin_sidebar_at_all(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/admin')->assertForbidden();
    }
}
