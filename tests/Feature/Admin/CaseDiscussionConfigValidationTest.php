<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the admin case editor's discussion_enabled / discussion_default_persona
 * / discussion_max_rounds fields for Phase 19 Milestone 3, per
 * docs/13-ai-discussion-engine-design.md §11.3. Milestone 2 built the
 * fields themselves; this milestone is their dedicated validation
 * coverage, per the roadmap's own split for Phase 19. Kept as its own
 * file rather than added to CaseManagementTest, matching this
 * implementation's per-feature test convention.
 */
class CaseDiscussionConfigValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(UserRole::Admin)->create();
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

    public function test_admin_can_enable_discussion_with_a_persona_and_max_rounds_override(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload([
            'discussion_enabled' => '1',
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => '5',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cases', [
            'slug' => 'login-failure-investigation',
            'discussion_enabled' => true,
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => 5,
        ]);
    }

    public function test_discussion_is_disabled_by_default_when_the_checkbox_is_omitted(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('cases', [
            'slug' => 'login-failure-investigation',
            'discussion_enabled' => false,
            'discussion_default_persona' => null,
            'discussion_max_rounds' => null,
        ]);
    }

    public function test_enabling_discussion_without_a_persona_fails_validation(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload([
            'discussion_enabled' => '1',
        ]));

        $response->assertSessionHasErrors('discussion_default_persona');
        $this->assertDatabaseMissing('cases', ['slug' => 'login-failure-investigation']);
    }

    public function test_an_unknown_persona_fails_validation(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload([
            'discussion_enabled' => '1',
            'discussion_default_persona' => 'not-a-real-persona',
        ]));

        $response->assertSessionHasErrors('discussion_default_persona');
    }

    public function test_max_rounds_must_be_a_positive_integer(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload([
            'discussion_enabled' => '1',
            'discussion_default_persona' => 'mentor',
            'discussion_max_rounds' => '0',
        ]));

        $response->assertSessionHasErrors('discussion_max_rounds');
    }

    public function test_max_rounds_can_be_left_blank_to_use_the_personas_default(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/cases', $this->validCasePayload([
            'discussion_enabled' => '1',
            'discussion_default_persona' => 'mentor',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cases', [
            'slug' => 'login-failure-investigation',
            'discussion_enabled' => true,
            'discussion_max_rounds' => null,
        ]);
    }

    public function test_admin_can_later_enable_discussion_on_an_existing_case_via_update(): void
    {
        $admin = $this->admin();
        $case = CaseModel::factory()->create(['discussion_enabled' => false]);

        $response = $this->actingAs($admin)->put("/admin/cases/{$case->id}", $this->validCasePayload([
            'slug' => $case->slug,
            'discussion_enabled' => '1',
            'discussion_default_persona' => 'interviewer',
            'discussion_max_rounds' => '4',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'discussion_enabled' => true,
            'discussion_default_persona' => 'interviewer',
            'discussion_max_rounds' => 4,
        ]);
    }
}
