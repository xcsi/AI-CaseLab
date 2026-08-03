<?php

namespace App\Discussion\Conformance;

use App\Enums\DiscussionVerdict;

/**
 * One student message within a GoldenTranscript (docs/13-ai-discussion-engine-design.md
 * §15.4) and, for turns the contract actually makes a claim about, the
 * verdict a conforming provider/model must produce in reply. Not every
 * turn is graded — e.g. transcript D's first turn exists only to set up
 * the contradiction its second turn tests, and §15.4's table lists no
 * expected verdict for it.
 */
final class GoldenTranscriptTurn
{
    public function __construct(
        public readonly string $studentMessage,
        public readonly ?DiscussionVerdict $expectedVerdict = null,
        public readonly bool $graded = true,
    ) {}
}
