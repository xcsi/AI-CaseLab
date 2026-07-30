<?php

namespace Tests\Feature\Discussion;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the "Start Engineering Discussion" workspace entry point for Phase
 * 18 Milestone 1, per docs/13-ai-discussion-engine-design.md §11.1: visible
 * only when case.discussion_enabled, a peer of Submit Diagnosis in the top
 * bar. No click behavior is tested here — this milestone is the entry
 * point only; the panel it opens is Milestone 2. Kept as its own test file
 * (not added to the existing InvestigationWorkspaceShellTest, a Version 1
 * asset) to keep every Version 2 UI addition's coverage separate, matching
 * this implementation's convention since Phase 13.
 */
class WorkspaceDiscussionEntryPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_entry_point_is_visible_and_correctly_urled_when_discussion_is_enabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('Start Engineering Discussion');
        $response->assertSee('id="workspace-discussion-start-button"', false);
        $response->assertSee(
            'data-discussion-start-url="'.route('investigation.discussion.start', $attempt).'"',
            false
        );
    }

    public function test_the_entry_point_is_absent_when_discussion_is_disabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => false]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('Start Engineering Discussion');
        $response->assertDontSee('workspace-discussion-start-button', false);
    }

    public function test_the_entry_point_is_absent_by_default_for_a_pre_version_2_case(): void
    {
        // discussion_enabled defaults to false for every existing Version 1
        // case (§9.3) — proven here by not setting it explicitly at all,
        // the same shape every case created before Version 2 existed has.
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('Start Engineering Discussion');
    }

    public function test_existing_workspace_elements_render_unaffected_alongside_the_new_entry_point(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['title' => 'API Returning 500', 'discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('API Returning 500');
        $response->assertSee('Submit Diagnosis');
        $response->assertSee('href="'.route('investigation.diagnosis.create', $attempt).'"', false);
        $response->assertSee('id="workspace-timer"', false);
    }
}
