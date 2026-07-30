<?php

namespace App\Discussion\Infrastructure\Llm\Support;

use App\Discussion\Exceptions\StructuredOutputParseException;
use App\Enums\DiscussionVerdict;

/**
 * The one place in the codebase responsible for interpreting a model's
 * structured reply (docs/13-ai-discussion-engine-design.md §1.4.6, §4.3).
 * Every LlmClientInterface implementation extracts the provider-specific
 * raw text content from its own response envelope, then hands that raw
 * text here — this class knows nothing about OpenAI, Anthropic, or Gemini,
 * only the JSON contract every persona's prompt asks the model to follow.
 *
 * Validates and normalizes, per the approved scope: required fields
 * ("reply_text", "verdict") are strictly required and cause
 * StructuredOutputParseException if missing or invalid — no defaulting, no
 * inference. Optional fields are normalized to sensible types/defaults,
 * which is a different, narrower thing than inferring a value for
 * something that's actually required and absent.
 */
final class StructuredOutputParser
{
    public function parse(string $rawContent): ParsedStructuredOutput
    {
        $decoded = json_decode($rawContent, true);

        if (! is_array($decoded)) {
            throw new StructuredOutputParseException(
                'Model reply did not decode to a JSON object.'
            );
        }

        if (! isset($decoded['reply_text']) || ! is_string($decoded['reply_text']) || $decoded['reply_text'] === '') {
            throw new StructuredOutputParseException(
                'Model reply is missing a valid "reply_text" field.'
            );
        }

        if (! isset($decoded['verdict']) || ! is_string($decoded['verdict']) || DiscussionVerdict::tryFrom($decoded['verdict']) === null) {
            throw new StructuredOutputParseException(
                'Model reply is missing a valid "verdict" field.'
            );
        }

        return new ParsedStructuredOutput(
            replyText: $decoded['reply_text'],
            verdict: $decoded['verdict'],
            evidenceReferenced: is_array($decoded['evidence_referenced'] ?? null) ? $decoded['evidence_referenced'] : null,
            internalNote: is_string($decoded['internal_note'] ?? null) ? $decoded['internal_note'] : null,
            evidenceGapDetected: (bool) ($decoded['evidence_gap_detected'] ?? false),
            contradictionDetected: (bool) ($decoded['contradiction_detected'] ?? false),
        );
    }
}
