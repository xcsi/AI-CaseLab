<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Infrastructure\Llm\Support\ParsedStructuredOutput;
use App\Discussion\Support\TurnClassifier;
use App\Enums\DiscussionVerdict;
use Tests\TestCase;

/**
 * Proves TurnClassifier for the Phase 14 Milestone 6 catch-up / Phase 15
 * Milestone 3, per docs/13-ai-discussion-engine-design.md §1.3/§4.3: given
 * an already-validated ParsedStructuredOutput, classify() is a direct,
 * mechanical read of the verdict field — no branching, no defaulting.
 * Deliberately given only well-formed input in every test here, since
 * StructuredOutputParser (not this class) is what guarantees "verdict" is
 * always a valid value by the time TurnClassifier ever sees it.
 */
class TurnClassifierTest extends TestCase
{
    public function test_it_classifies_continue(): void
    {
        $parsed = $this->parsedWithVerdict('continue');

        $this->assertSame(DiscussionVerdict::Continue, (new TurnClassifier())->classify($parsed));
    }

    public function test_it_classifies_accept(): void
    {
        $parsed = $this->parsedWithVerdict('accept');

        $this->assertSame(DiscussionVerdict::Accept, (new TurnClassifier())->classify($parsed));
    }

    public function test_it_classifies_end_unresolved(): void
    {
        $parsed = $this->parsedWithVerdict('end_unresolved');

        $this->assertSame(DiscussionVerdict::EndUnresolved, (new TurnClassifier())->classify($parsed));
    }

    private function parsedWithVerdict(string $verdict): ParsedStructuredOutput
    {
        return new ParsedStructuredOutput(
            replyText: 'reply',
            verdict: $verdict,
            evidenceReferenced: null,
            internalNote: null,
            evidenceGapDetected: false,
            contradictionDetected: false,
        );
    }
}
