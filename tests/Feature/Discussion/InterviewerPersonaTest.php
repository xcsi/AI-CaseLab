<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Personas\InterviewerPersona;
use App\Models\DiscussionSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves InterviewerPersona for Phase 15 Milestone 1, per
 * docs/13-ai-discussion-engine-design.md §3/§3.1: correct config-driven
 * prompt fragment and acceptance bar, and that it never offers a hint —
 * proven at both a low and a high round count, so "always false" is a
 * verified behavior, not a coincidence of one test case.
 */
class InterviewerPersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prompt_fragment_reflects_configured_tone_and_acceptance_bar(): void
    {
        $fragment = (new InterviewerPersona())->systemPromptFragment();

        $this->assertStringContainsString('Technical Interview', $fragment);
        $this->assertStringContainsString('terse', $fragment);
        $this->assertStringContainsString('skeptical', $fragment);
        $this->assertStringContainsString('evaluative', $fragment);
        $this->assertStringContainsString('fully specified root cause + fix + all claims evidence-backed', $fragment);
        $this->assertStringContainsString('Do not offer hints', $fragment);
        $this->assertStringContainsString('never about the person', $fragment);
    }

    public function test_acceptance_bar_matches_config_exactly(): void
    {
        $this->assertSame(
            'fully specified root cause + fix + all claims evidence-backed',
            (new InterviewerPersona())->acceptanceBar()
        );
    }

    public function test_it_never_offers_a_hint_at_a_low_round_count(): void
    {
        $session = DiscussionSession::factory()->create(['round_count' => 0]);

        $this->assertFalse((new InterviewerPersona())->shouldOfferHint($session));
    }

    public function test_it_never_offers_a_hint_even_at_a_very_high_round_count(): void
    {
        $session = DiscussionSession::factory()->create(['round_count' => 100]);

        $this->assertFalse((new InterviewerPersona())->shouldOfferHint($session));
    }
}
