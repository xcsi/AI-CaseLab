<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricCriterionManagementTest extends TestCase
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

    public function test_admin_can_view_rubric_criteria_on_the_case_edit_page(): void
    {
        $case = CaseModel::factory()->create();
        RubricCriterion::factory()->create(['case_id' => $case->id, 'title' => 'Identifies the N+1 query']);

        $response = $this->actingAs($this->admin())->get("/admin/cases/{$case->id}/edit");

        $response->assertOk();
        $response->assertSee('Identifies the N+1 query');
    }

    public function test_admin_can_add_a_keyword_criterion(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Identifies session cookie misconfiguration',
            'description' => 'Root cause correctly named.',
            'weight' => 10,
            'matching_type' => 'keyword',
            'keywords' => "session cookie\ndomain mismatch",
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $criterion = RubricCriterion::first();
        $this->assertNotNull($criterion);
        $this->assertSame(['session cookie', 'domain mismatch'], $criterion->expected_data['keywords']);
        $this->assertSame('10.00', $criterion->weight);
    }

    public function test_adding_a_criterion_recalculates_the_case_max_score(): void
    {
        $case = CaseModel::factory()->create(['max_score' => 0]);
        RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 5]);

        $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Second criterion',
            'weight' => 15,
            'matching_type' => 'manual',
        ]);

        $this->assertSame('20.00', $case->fresh()->max_score);
    }

    public function test_adding_a_criterion_to_a_published_case_bumps_its_version(): void
    {
        $case = CaseModel::factory()->published()->create(['version' => 1]);

        $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Criterion',
            'weight' => 10,
            'matching_type' => 'manual',
        ]);

        $this->assertSame(2, $case->fresh()->version);
    }

    public function test_a_keyword_criterion_requires_at_least_one_keyword(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Criterion',
            'weight' => 10,
            'matching_type' => 'keyword',
            'keywords' => '',
        ]);

        $response->assertSessionHasErrors('expected_data');
        $this->assertSame(0, RubricCriterion::count());
    }

    public function test_a_manual_criterion_does_not_require_keywords(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Reviewed by instructor',
            'weight' => 10,
            'matching_type' => 'manual',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame([], RubricCriterion::first()->expected_data);
    }

    public function test_an_evidence_citation_criterion_defaults_to_no_required_evidence(): void
    {
        $case = CaseModel::factory()->create();

        $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Cites the nginx log',
            'weight' => 10,
            'matching_type' => 'evidence_citation',
        ]);

        $this->assertSame(['required_evidence_ids' => []], RubricCriterion::first()->expected_data);
    }

    public function test_instructor_cannot_add_a_criterion(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->instructor())->post("/admin/cases/{$case->id}/rubric-criteria", [
            'title' => 'Should not be added',
            'weight' => 10,
            'matching_type' => 'manual',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, RubricCriterion::count());
    }

    public function test_admin_can_update_a_criterion(): void
    {
        $criterion = RubricCriterion::factory()->create(['title' => 'Old title', 'weight' => 5]);

        $response = $this->actingAs($this->admin())->put("/admin/rubric-criteria/{$criterion->id}", [
            'title' => 'New title',
            'weight' => 8,
            'matching_type' => 'manual',
        ]);

        $response->assertRedirect();
        $this->assertSame('New title', $criterion->fresh()->title);
        $this->assertSame('8.00', $criterion->fresh()->weight);
    }

    public function test_updating_a_criterion_recalculates_the_case_max_score(): void
    {
        $case = CaseModel::factory()->create();
        $criterion = RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 5]);

        $this->actingAs($this->admin())->put("/admin/rubric-criteria/{$criterion->id}", [
            'title' => $criterion->title,
            'weight' => 12,
            'matching_type' => 'manual',
        ]);

        $this->assertSame('12.00', $case->fresh()->max_score);
    }

    public function test_instructor_cannot_update_a_criterion(): void
    {
        $criterion = RubricCriterion::factory()->create();

        $response = $this->actingAs($this->instructor())->put("/admin/rubric-criteria/{$criterion->id}", [
            'title' => 'New title',
            'weight' => 8,
            'matching_type' => 'manual',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_criterion(): void
    {
        $criterion = RubricCriterion::factory()->create();

        $response = $this->actingAs($this->admin())->delete("/admin/rubric-criteria/{$criterion->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('rubric_criteria', ['id' => $criterion->id]);
    }

    public function test_deleting_a_criterion_recalculates_the_case_max_score(): void
    {
        $case = CaseModel::factory()->create();
        $keep = RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 5]);
        $remove = RubricCriterion::factory()->create(['case_id' => $case->id, 'weight' => 15]);

        $this->actingAs($this->admin())->delete("/admin/rubric-criteria/{$remove->id}");

        $this->assertSame('5.00', $case->fresh()->max_score);
        $this->assertDatabaseHas('rubric_criteria', ['id' => $keep->id]);
    }

    public function test_instructor_cannot_delete_a_criterion(): void
    {
        $criterion = RubricCriterion::factory()->create();

        $response = $this->actingAs($this->instructor())->delete("/admin/rubric-criteria/{$criterion->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('rubric_criteria', ['id' => $criterion->id]);
    }
}
