<?php

namespace Tests\Feature\Discussion;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Engineering Discussion chat-style panel markup for Phase 18
 * Milestone 2, per docs/13-ai-discussion-engine-design.md §11.1: a
 * dark-panel modal (reusing the evidence viewers' visual language), a
 * round counter, an "AI is thinking…" state, and the fetch-driven forms
 * that drive DiscussionController's start/respond endpoints. "End
 * Discussion" (Milestone 3) and the dedicated unavailable-state UI
 * (Milestone 4) are out of scope and not asserted here. Kept as its own
 * test file, matching this implementation's per-milestone UI test
 * convention since Phase 13.
 */
class WorkspaceDiscussionPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_panel_markup_is_present_when_discussion_is_enabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('id="discussion-panel-modal"', false);
        $response->assertSee('id="discussion-round-counter"', false);
        $response->assertSee('id="discussion-transcript"', false);
        $response->assertSee('id="discussion-thinking"', false);
        $response->assertSee('AI is thinking');
        $response->assertSee('id="discussion-open-form"', false);
        $response->assertSee('id="discussion-open-input"', false);
        $response->assertSee('id="discussion-reply-form"', false);
        $response->assertSee('id="discussion-reply-input"', false);
        $response->assertSee('id="discussion-error"', false);
    }

    public function test_the_panel_markup_is_absent_when_discussion_is_disabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => false]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        // The panel and its trigger are both conditioned on discussion_enabled
        // together — proving the trigger's absence (already covered by
        // WorkspaceDiscussionEntryPointTest) plus the panel's own markup
        // absence here confirms neither renders orphaned from the other.
        $response->assertDontSee('id="discussion-panel-modal"', false);
        $response->assertDontSee('workspace-discussion-start-button', false);
    }

    public function test_existing_workspace_elements_render_unaffected_alongside_the_panel(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['title' => 'API Returning 500', 'discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('API Returning 500');
        $response->assertSee('Submit Diagnosis');
        $response->assertSee('id="notebook-textarea"', false);
        $response->assertSee('id="workspace-timer"', false);
    }
}
