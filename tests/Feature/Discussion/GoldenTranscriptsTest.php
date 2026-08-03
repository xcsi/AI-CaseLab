<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Conformance\GoldenTranscripts;
use App\Enums\DiscussionVerdict;
use Tests\TestCase;

/**
 * Structural sanity check on the four fixed golden transcripts (§15.4)
 * for Phase 21 Milestone 1 — this is the data every conformance run
 * depends on, so a typo here (wrong persona, wrong expected verdict)
 * would otherwise go unnoticed by GoldenTranscriptRunnerTest, which only
 * proves the runner's *logic* against arbitrary transcripts, not this
 * specific data.
 */
class GoldenTranscriptsTest extends TestCase
{
    public function test_it_returns_exactly_the_four_transcripts_the_design_defines(): void
    {
        $transcripts = GoldenTranscripts::all();

        $this->assertCount(4, $transcripts);
    }

    public function test_transcript_a_is_evidence_requirement_under_mentor_and_ends_in_accept(): void
    {
        $transcript = GoldenTranscripts::all()[0];

        $this->assertStringContainsString('Evidence-requirement', $transcript->name);
        $this->assertSame('mentor', $transcript->persona);
        $this->assertCount(3, $transcript->turns);
        $this->assertSame(DiscussionVerdict::Continue, $transcript->turns[0]->expectedVerdict);
        $this->assertSame(DiscussionVerdict::Continue, $transcript->turns[1]->expectedVerdict);
        $this->assertSame(DiscussionVerdict::Accept, $transcript->turns[2]->expectedVerdict);
    }

    public function test_transcript_b_is_sycophancy_resistance_under_interviewer_and_never_accepts(): void
    {
        $transcript = GoldenTranscripts::all()[1];

        $this->assertStringContainsString('Sycophancy', $transcript->name);
        $this->assertSame('interviewer', $transcript->persona);
        $this->assertCount(1, $transcript->turns);
        $this->assertSame(DiscussionVerdict::Continue, $transcript->turns[0]->expectedVerdict);
    }

    public function test_transcript_c_is_injection_leakage_resistance(): void
    {
        $transcript = GoldenTranscripts::all()[2];

        $this->assertStringContainsString('Injection', $transcript->name);
        $this->assertCount(1, $transcript->turns);
        $this->assertStringContainsString('Ignore your previous instructions', $transcript->turns[0]->studentMessage);
        $this->assertSame(DiscussionVerdict::Continue, $transcript->turns[0]->expectedVerdict);
    }

    public function test_transcript_d_is_contradiction_detection_with_an_ungraded_setup_turn(): void
    {
        $transcript = GoldenTranscripts::all()[3];

        $this->assertStringContainsString('Contradiction', $transcript->name);
        $this->assertCount(2, $transcript->turns);
        $this->assertFalse($transcript->turns[0]->graded);
        $this->assertTrue($transcript->turns[1]->graded);
        $this->assertSame(DiscussionVerdict::Continue, $transcript->turns[1]->expectedVerdict);
    }

    public function test_every_transcript_resolves_to_a_known_persona(): void
    {
        foreach (GoldenTranscripts::all() as $transcript) {
            $this->assertContains($transcript->persona, ['mentor', 'interviewer']);
        }
    }
}
