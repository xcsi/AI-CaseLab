<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Personas\MentorPersona;
use App\Models\DiscussionSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves MentorPersona for Phase 15 Milestone 1, per
 * docs/13-ai-discussion-engine-design.md §3/§3.1: correct config-driven
 * prompt fragment and acceptance bar, and the one piece of real behavior
 * this persona has — offering a soft nudge only once a student has stalled
 * for stall_threshold rounds (3, per config/discussion_personas.php), never
 * before.
 */
class MentorPersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prompt_fragment_reflects_configured_tone_and_acceptance_bar(): void
    {
        $fragment = (new MentorPersona())->systemPromptFragment();

        $this->assertStringContainsString('Mentor Review', $fragment);
        $this->assertStringContainsString('encouraging', $fragment);
        $this->assertStringContainsString('curious', $fragment);
        $this->assertStringContainsString('collaborative', $fragment);
        $this->assertStringContainsString('directionally correct + at least one evidence citation', $fragment);
        $this->assertStringContainsString('never state the root cause or fix outright', $fragment);
    }

    public function test_acceptance_bar_matches_config_exactly(): void
    {
        $this->assertSame(
            'directionally correct + at least one evidence citation',
            (new MentorPersona())->acceptanceBar()
        );
    }

    public function test_it_does_not_offer_a_hint_before_the_stall_threshold(): void
    {
        $session = DiscussionSession::factory()->create(['round_count' => 2]);

        $this->assertFalse((new MentorPersona())->shouldOfferHint($session));
    }

    public function test_it_offers_a_hint_once_the_stall_threshold_is_reached(): void
    {
        $session = DiscussionSession::factory()->create(['round_count' => 3]);

        $this->assertTrue((new MentorPersona())->shouldOfferHint($session));
    }

    public function test_it_still_offers_a_hint_well_past_the_stall_threshold(): void
    {
        $session = DiscussionSession::factory()->create(['round_count' => 7]);

        $this->assertTrue((new MentorPersona())->shouldOfferHint($session));
    }
}
