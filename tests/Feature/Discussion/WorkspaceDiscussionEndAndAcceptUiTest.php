<?php

namespace Tests\Feature\Discussion;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the "End Discussion" confirmation flow and the accept ->
 * diagnosis-prefill transition banner markup for Phase 18 Milestone 3, per
 * docs/13-ai-discussion-engine-design.md §11.1. The dedicated "AI
 * Discussion Unavailable" state (Milestone 4) is out of scope and not
 * asserted here. Kept as its own test file, matching this implementation's
 * per-milestone UI test convention since Phase 13.
 */
class WorkspaceDiscussionEndAndAcceptUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_end_discussion_and_accepted_transition_markup_is_present_when_discussion_is_enabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('id="discussion-end-button"', false);
        $response->assertSee('id="discussion-end-confirm"', false);
        $response->assertSee('id="discussion-end-confirm-proceed"', false);
        $response->assertSee('id="discussion-end-confirm-cancel"', false);
        $response->assertSee('id="discussion-accepted-banner"', false);
        $response->assertSee(
            'href="'.route('investigation.diagnosis.create', $attempt).'"',
            false
        );
    }

    public function test_the_end_discussion_and_accepted_transition_markup_is_absent_when_discussion_is_disabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => false]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('discussion-end-button', false);
        $response->assertDontSee('discussion-end-confirm', false);
        $response->assertDontSee('discussion-accepted-banner', false);
    }
}
