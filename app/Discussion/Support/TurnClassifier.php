<?php

namespace App\Discussion\Support;

use App\Discussion\Infrastructure\Llm\Support\ParsedStructuredOutput;
use App\Enums\DiscussionVerdict;

/**
 * Extracts the verdict (and the two detection flags) from an already-
 * validated ParsedStructuredOutput (docs/13-ai-discussion-engine-design.md
 * §1.3, §4.3). Deliberately downstream of StructuredOutputParser, not a
 * second place that re-validates or guesses: by the time this class runs,
 * "verdict" is already guaranteed to be one of DiscussionVerdict's cases,
 * so classify() is a direct, mechanical read — no branching on missing
 * data, no defaulting, no decisions. "TurnClassifier reads verdict
 * directly; it doesn't guess from prose" (§4.3) is true by construction
 * here, not by convention.
 */
final class TurnClassifier
{
    public function classify(ParsedStructuredOutput $parsed): DiscussionVerdict
    {
        return DiscussionVerdict::from($parsed->verdict);
    }
}
