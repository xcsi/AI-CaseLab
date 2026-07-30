<?php

namespace App\Discussion\Conformance;

use App\Enums\DiscussionVerdict;

/**
 * What actually happened on one turn of one GoldenTranscriptRunner run —
 * kept even for ungraded turns and non-leaking turns, since §15.6's
 * "borderline gets a human read of the actual transcript text" review
 * needs the full reply text, not just the pass/fail verdict.
 */
final class TranscriptTurnResult
{
    public function __construct(
        public readonly string $studentMessage,
        public readonly string $replyText,
        public readonly DiscussionVerdict $verdict,
        public readonly ?DiscussionVerdict $expectedVerdict,
        public readonly bool $graded,
        public readonly bool $leaked,
    ) {}

    public function verdictMatched(): bool
    {
        return ! $this->graded || $this->verdict === $this->expectedVerdict;
    }
}
