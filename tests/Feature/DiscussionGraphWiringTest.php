<?php

namespace Tests\Feature;

use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Models\DiscussionTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proof-of-wiring test for Phase 13 (per docs/14-v2-implementation-roadmap.md's
 * Milestone 6 acceptance criteria, mirroring DomainGraphWiringTest's role for
 * Phase 3): attaches a DiscussionSession to a real seeded CaseAttempt via the
 * polymorphic relation, with several DiscussionTurns, and walks every
 * relationship before any Service/Controller exists on top of this schema.
 *
 * CaseAttempt itself is not touched or extended with a reverse relation —
 * per docs/13-ai-discussion-engine-design.md §1.5, Version 1 models stay
 * exactly as they are; the polymorphic link is one-directional by design
 * (DiscussionSession -> discussable), so "both directions" below covers the
 * relationships that actually have two sides: turns <-> session.
 */
class DiscussionGraphWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_discussion_session_can_be_attached_to_a_real_case_attempt_and_traversed(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $session = DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
            'persona' => 'mentor',
            'max_rounds' => 6,
        ]);

        $studentTurn = DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 1,
            'role' => DiscussionTurnRole::Student->value,
            'content' => 'I think the payment gateway is just down sometimes.',
        ]);

        $aiTurn = DiscussionTurn::factory()->create([
            'discussion_session_id' => $session->id,
            'sequence_order' => 2,
            'role' => DiscussionTurnRole::Ai->value,
            'content' => 'What in the evidence points to it being down specifically?',
            'verdict' => DiscussionVerdict::Continue->value,
            'evidence_referenced' => [1, 2],
            'internal_note' => 'No evidence citation yet — asked for one.',
            'prompt_tokens' => 812,
            'completion_tokens' => 47,
            'provider' => 'ollama',
            'model' => 'qwen2.5:7b',
        ]);

        // Session -> discussable (polymorphic, forward direction — the only
        // direction this relation has by design)
        $this->assertTrue($session->discussable->is($attempt));
        $this->assertInstanceOf(CaseAttempt::class, $session->discussable);

        // Session -> turns, and turns -> session (both directions)
        $this->assertCount(2, $session->turns);
        $this->assertTrue($session->turns->first()->is($studentTurn));
        $this->assertTrue($session->turns->last()->is($aiTurn));
        $this->assertTrue($studentTurn->session->is($session));
        $this->assertTrue($aiTurn->session->is($session));

        // Enum casts survive the round trip through the database
        $this->assertSame(DiscussionStatus::Active, $session->fresh()->status);
        $this->assertSame(DiscussionTurnRole::Student, $studentTurn->fresh()->role);
        $this->assertSame(DiscussionTurnRole::Ai, $aiTurn->fresh()->role);
        $this->assertSame(DiscussionVerdict::Continue, $aiTurn->fresh()->verdict);
        $this->assertNull($studentTurn->fresh()->verdict);

        // JSON casts survive the round trip
        $this->assertSame([1, 2], $aiTurn->fresh()->evidence_referenced);

        // discussion_turns has no updated_at column — confirms Milestone 4's
        // $timestamps = false didn't silently break created_at population
        $this->assertNotNull($studentTurn->fresh()->created_at);
    }
}
