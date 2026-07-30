<?php

namespace App\Discussion\Infrastructure\Llm\Support;

/**
 * The validated, normalized output of StructuredOutputParser::parse() — the
 * one shape every provider client hands to TurnClassifier, regardless of
 * which provider answered or whether the raw reply came from a native
 * tool-call or a strict-JSON prompt contract (docs/13-ai-discussion-engine-design.md
 * §1.4.6). By the time this object exists, "reply_text" and "verdict" are
 * guaranteed present and well-formed — that guarantee is the entire point
 * of it being a distinct type from the raw decoded array.
 */
final class ParsedStructuredOutput
{
    /**
     * @param  array<int, int>|null  $evidenceReferenced
     */
    public function __construct(
        public readonly string $replyText,
        public readonly string $verdict,
        public readonly ?array $evidenceReferenced,
        public readonly ?string $internalNote,
        public readonly bool $evidenceGapDetected,
        public readonly bool $contradictionDetected,
    ) {}
}
