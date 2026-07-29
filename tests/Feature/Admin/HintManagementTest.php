<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\Hint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HintManagementTest extends TestCase
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

    public function test_admin_can_view_hints_on_the_case_edit_page(): void
    {
        $case = CaseModel::factory()->create();
        Hint::factory()->create(['case_id' => $case->id, 'content' => 'Check the nginx access log.']);

        $response = $this->actingAs($this->admin())->get("/admin/cases/{$case->id}/edit");

        $response->assertOk();
        $response->assertSee('Check the nginx access log.');
    }

    public function test_admin_can_add_a_hint_to_a_case(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/hints", [
            'content' => 'Look at the recent deploy history.',
            'score_penalty' => 2.5,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('hints', [
            'case_id' => $case->id,
            'content' => 'Look at the recent deploy history.',
            'score_penalty' => 2.5,
            'order_index' => 0,
        ]);
    }

    public function test_new_hints_are_appended_to_the_end_of_the_unlock_order(): void
    {
        $case = CaseModel::factory()->create();
        Hint::factory()->create(['case_id' => $case->id, 'order_index' => 0]);
        Hint::factory()->create(['case_id' => $case->id, 'order_index' => 1]);

        $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/hints", [
            'content' => 'Third hint.',
            'score_penalty' => 1,
        ]);

        $this->assertDatabaseHas('hints', ['content' => 'Third hint.', 'order_index' => 2]);
    }

    public function test_guest_cannot_add_a_hint(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->post("/admin/cases/{$case->id}/hints", [
            'content' => 'Should not be added.',
            'score_penalty' => 1,
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('hints', ['content' => 'Should not be added.']);
    }

    public function test_instructor_cannot_add_a_hint(): void
    {
        $case = CaseModel::factory()->create();

        $response = $this->actingAs($this->instructor())->post("/admin/cases/{$case->id}/hints", [
            'content' => 'Should not be added.',
            'score_penalty' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('hints', ['content' => 'Should not be added.']);
    }

    public function test_admin_can_update_a_hint(): void
    {
        $hint = Hint::factory()->create(['content' => 'Old content', 'score_penalty' => 1]);

        $response = $this->actingAs($this->admin())->put("/admin/hints/{$hint->id}", [
            'content' => 'New content',
            'score_penalty' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('hints', ['id' => $hint->id, 'content' => 'New content', 'score_penalty' => 3]);
    }

    public function test_instructor_cannot_update_a_hint(): void
    {
        $hint = Hint::factory()->create();

        $response = $this->actingAs($this->instructor())->put("/admin/hints/{$hint->id}", [
            'content' => 'New content',
            'score_penalty' => 3,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_hint(): void
    {
        $hint = Hint::factory()->create();

        $response = $this->actingAs($this->admin())->delete("/admin/hints/{$hint->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('hints', ['id' => $hint->id]);
    }

    public function test_guest_cannot_delete_a_hint(): void
    {
        $hint = Hint::factory()->create();

        $response = $this->delete("/admin/hints/{$hint->id}");

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('hints', ['id' => $hint->id]);
    }

    public function test_instructor_cannot_delete_a_hint(): void
    {
        $hint = Hint::factory()->create();

        $response = $this->actingAs($this->instructor())->delete("/admin/hints/{$hint->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('hints', ['id' => $hint->id]);
    }

    public function test_admin_can_move_a_hint_up_swapping_order_with_its_predecessor(): void
    {
        $case = CaseModel::factory()->create();
        $first = Hint::factory()->create(['case_id' => $case->id, 'order_index' => 0]);
        $second = Hint::factory()->create(['case_id' => $case->id, 'order_index' => 1]);

        $this->actingAs($this->admin())->post("/admin/hints/{$second->id}/move-up");

        $this->assertSame(0, $second->fresh()->order_index);
        $this->assertSame(1, $first->fresh()->order_index);
    }

    public function test_admin_can_move_a_hint_down_swapping_order_with_its_successor(): void
    {
        $case = CaseModel::factory()->create();
        $first = Hint::factory()->create(['case_id' => $case->id, 'order_index' => 0]);
        $second = Hint::factory()->create(['case_id' => $case->id, 'order_index' => 1]);

        $this->actingAs($this->admin())->post("/admin/hints/{$first->id}/move-down");

        $this->assertSame(1, $first->fresh()->order_index);
        $this->assertSame(0, $second->fresh()->order_index);
    }

    public function test_moving_the_first_hint_up_is_a_no_op(): void
    {
        $case = CaseModel::factory()->create();
        $first = Hint::factory()->create(['case_id' => $case->id, 'order_index' => 0]);

        $this->actingAs($this->admin())->post("/admin/hints/{$first->id}/move-up");

        $this->assertSame(0, $first->fresh()->order_index);
    }

    public function test_instructor_cannot_reorder_hints(): void
    {
        $hint = Hint::factory()->create(['order_index' => 1]);

        $response = $this->actingAs($this->instructor())->post("/admin/hints/{$hint->id}/move-up");

        $response->assertForbidden();
    }

    public function test_adding_a_hint_to_a_published_case_bumps_its_version(): void
    {
        $case = CaseModel::factory()->published()->create(['version' => 1]);

        $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/hints", [
            'content' => 'A new hint.',
            'score_penalty' => 1,
        ]);

        $this->assertSame(2, $case->fresh()->version);
    }

    public function test_adding_a_hint_to_a_draft_case_does_not_bump_its_version(): void
    {
        $case = CaseModel::factory()->create(['version' => 1]);

        $this->actingAs($this->admin())->post("/admin/cases/{$case->id}/hints", [
            'content' => 'A new hint.',
            'score_penalty' => 1,
        ]);

        $this->assertSame(1, $case->fresh()->version);
    }

    public function test_deleting_a_hint_from_a_published_case_bumps_its_version(): void
    {
        $case = CaseModel::factory()->published()->create(['version' => 1]);
        $hint = Hint::factory()->create(['case_id' => $case->id]);

        $this->actingAs($this->admin())->delete("/admin/hints/{$hint->id}");

        $this->assertSame(2, $case->fresh()->version);
    }
}
