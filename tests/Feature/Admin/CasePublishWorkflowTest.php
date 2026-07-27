<?php

namespace Tests\Feature\Admin;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasePublishWorkflowTest extends TestCase
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

    public function test_admin_can_publish_a_case_with_a_rubric_criterion(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);

        $response = $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/publish");

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame(CaseStatus::Published, $case->fresh()->status);
    }

    public function test_publishing_a_case_without_a_rubric_criterion_fails_and_leaves_it_draft(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/publish");

        $response->assertSessionHasErrors('publish');
        $this->assertSame(CaseStatus::Draft, $case->fresh()->status);
    }

    public function test_the_edit_page_shows_why_a_draft_case_cannot_be_published(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->get("/admin/cases/{$case->id}/edit");

        $response->assertOk();
        $response->assertSee('The case needs at least one rubric criterion.');
        $response->assertSee('disabled', false);
    }

    public function test_the_edit_page_shows_the_case_is_ready_once_invariants_are_met(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);

        $response = $this->actingAs($this->admin())->get("/admin/cases/{$case->id}/edit");

        $response->assertOk();
        $response->assertSee('This case is ready to publish.');
    }

    public function test_a_published_case_does_not_show_the_publish_card(): void
    {
        $case = CaseModel::factory()->published()->create();

        $response = $this->actingAs($this->admin())->get("/admin/cases/{$case->id}/edit");

        $response->assertOk();
        $response->assertDontSee('This case is ready to publish.');
    }

    public function test_instructor_cannot_publish_a_case(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 10]);

        $response = $this->actingAs($this->instructor())->post("/admin/cases/{$case->id}/publish");

        $response->assertForbidden();
        $this->assertSame(CaseStatus::Draft, $case->fresh()->status);
    }
}
