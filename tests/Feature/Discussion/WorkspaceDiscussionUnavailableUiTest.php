<?php

namespace Tests\Feature\Discussion;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the "AI Discussion Unavailable" state markup for Phase 18
 * Milestone 4 (docs/13-ai-discussion-engine-design.md §11.5): a dedicated,
 * neutral-toned block distinct from the generic #discussion-error and the
 * green accept-transition banner, with a "Try Again" action — and that no
 * provider name ever appears in the Investigation Workspace's rendered
 * output. Kept as its own test file, matching this implementation's
 * per-milestone UI test convention since Phase 13.
 */
class WorkspaceDiscussionUnavailableUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unavailable_state_markup_is_present_when_discussion_is_enabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('id="discussion-unavailable"', false);
        $response->assertSee('id="discussion-unavailable-message"', false);
        $response->assertSee('id="discussion-unavailable-retry"', false);
        $response->assertSee('Try Again');
    }

    public function test_the_unavailable_state_markup_is_absent_when_discussion_is_disabled(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => false]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('discussion-unavailable', false);
    }

    public function test_no_provider_name_appears_anywhere_in_the_workspace_response(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $content = $response->getContent();

        foreach (['OpenAI', 'Anthropic', 'Gemini', 'Ollama', 'OpenRouter', 'Claude', 'GPT'] as $providerName) {
            $this->assertStringNotContainsStringIgnoringCase($providerName, $content);
        }
    }

    public function test_the_unavailable_message_element_ships_empty_not_hardcoded(): void
    {
        // The unavailable message text must come only from the server
        // response at runtime (DiscussionController's already-generic,
        // provider-agnostic copy) — proven here by asserting the template
        // ships the message element empty, not pre-filled with copy of
        // its own that could drift from the server's wording.
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['discussion_enabled' => true]);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('<p class="mb-2" id="discussion-unavailable-message"></p>', false);
    }
}
