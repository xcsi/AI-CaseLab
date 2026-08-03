<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design System v1, Milestone 3 — a "Skip to content" link must be the
 * first focusable element on every authenticated shell, jumping to the
 * page's main content region.
 */
class SkipLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_student_shell_has_a_skip_link_targeting_main_content(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['Skip to content', 'id="main-content"'], false);
        $response->assertSee('href="#main-content"', false);
    }

    public function test_the_admin_shell_has_a_skip_link_targeting_main_content(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['Skip to content', 'id="main-content"'], false);
        $response->assertSee('href="#main-content"', false);
    }
}
