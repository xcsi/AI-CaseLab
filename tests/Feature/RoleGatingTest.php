<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_area(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    public function test_student_is_forbidden_from_admin_area(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/admin');

        $response->assertForbidden();
    }

    public function test_instructor_is_forbidden_from_admin_area(): void
    {
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $response = $this->actingAs($instructor)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_can_reach_admin_area(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
    }

    public function test_new_registrations_default_to_the_student_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Engineer',
            'email' => 'new-engineer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'new-engineer@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(UserRole::Student));
    }
}
