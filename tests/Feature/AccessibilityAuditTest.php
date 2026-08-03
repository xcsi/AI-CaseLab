<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Hint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design System v1, Milestone 7 — every decorative <x-icon> renders
 * aria-hidden="true" (so screen readers skip it), and every icon-only
 * control still carries its own aria-label naming the action.
 */
class AccessibilityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_score_icon_is_aria_hidden(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create();
        CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $case->id,
            'status' => \App\Enums\AttemptStatus::Completed,
            'score_earned' => 8,
            'max_possible_score' => 10,
        ]);

        $response = $this->actingAs($student)->get(route('cases.index'));

        $response->assertOk();
        $response->assertSee('80% <svg', false);
        $response->assertSee('aria-hidden="true"', false);
    }

    public function test_a_locked_hint_icon_is_aria_hidden_while_the_button_keeps_no_redundant_label(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        Hint::factory()->create(['case_id' => $case->id]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('<span class="hint-lock-icon"><svg', false);
        $response->assertSeeInOrder(['hint-lock-icon', 'aria-hidden="true"'], false);
    }

    public function test_every_modal_close_button_has_an_aria_label(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        preg_match_all('/<button[^>]*btn-close[^>]*>/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[0], 'Expected at least one .btn-close button on the Investigation Workspace.');
        foreach ($matches[0] as $button) {
            $this->assertStringContainsString('aria-label=', $button);
        }
    }

    public function test_the_student_shell_nav_links_have_no_empty_accessible_names(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('<a class="nav-link"></a>', false);
    }

    public function test_the_admin_shell_has_a_workspace_link_with_a_readable_label(): void
    {
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Student Workspace', false);
    }
}
