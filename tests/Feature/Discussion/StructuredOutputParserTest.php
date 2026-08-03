<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\StructuredOutputParseException;
use App\Discussion\Infrastructure\Llm\Support\StructuredOutputParser;
use Tests\TestCase;

/**
 * Proves StructuredOutputParser for the Phase 14 Milestone 6 catch-up, per
 * docs/13-ai-discussion-engine-design.md §1.4.6/§4.3: required fields
 * ("reply_text", "verdict") throw when missing or invalid — no defaulting,
 * no inference — while optional fields normalize to sensible types. No
 * network call anywhere in this class or its tests; it operates purely on
 * an already-retrieved string.
 */
class StructuredOutputParserTest extends TestCase
{
    public function test_it_parses_a_fully_populated_valid_reply(): void
    {
        $parsed = (new StructuredOutputParser())->parse(json_encode([
            'reply_text' => 'What in the evidence supports that?',
            'verdict' => 'continue',
            'evidence_referenced' => [1, 2],
            'internal_note' => 'No citation yet.',
            'evidence_gap_detected' => true,
            'contradiction_detected' => false,
        ]));

        $this->assertSame('What in the evidence supports that?', $parsed->replyText);
        $this->assertSame('continue', $parsed->verdict);
        $this->assertSame([1, 2], $parsed->evidenceReferenced);
        $this->assertSame('No citation yet.', $parsed->internalNote);
        $this->assertTrue($parsed->evidenceGapDetected);
        $this->assertFalse($parsed->contradictionDetected);
    }

    public function test_it_normalizes_absent_optional_fields_to_sensible_defaults(): void
    {
        $parsed = (new StructuredOutputParser())->parse(json_encode([
            'reply_text' => 'Tell me more.',
            'verdict' => 'accept',
        ]));

        $this->assertNull($parsed->evidenceReferenced);
        $this->assertNull($parsed->internalNote);
        $this->assertFalse($parsed->evidenceGapDetected);
        $this->assertFalse($parsed->contradictionDetected);
    }

    public function test_it_throws_when_the_content_is_not_valid_json(): void
    {
        $this->expectException(StructuredOutputParseException::class);
        $this->expectExceptionMessage('did not decode to a JSON object');

        (new StructuredOutputParser())->parse('this is not json at all');
    }

    public function test_it_throws_when_reply_text_is_missing(): void
    {
        $this->expectException(StructuredOutputParseException::class);
        $this->expectExceptionMessage('reply_text');

        (new StructuredOutputParser())->parse(json_encode(['verdict' => 'continue']));
    }

    public function test_it_throws_when_reply_text_is_an_empty_string(): void
    {
        $this->expectException(StructuredOutputParseException::class);

        (new StructuredOutputParser())->parse(json_encode(['reply_text' => '', 'verdict' => 'continue']));
    }

    public function test_it_throws_when_verdict_is_missing(): void
    {
        $this->expectException(StructuredOutputParseException::class);
        $this->expectExceptionMessage('verdict');

        (new StructuredOutputParser())->parse(json_encode(['reply_text' => 'hello']));
    }

    public function test_it_throws_when_verdict_is_not_a_recognized_value(): void
    {
        // This is the specific case a lenient parser would be tempted to
        // default to "continue" — StructuredOutputParser must not.
        $this->expectException(StructuredOutputParseException::class);
        $this->expectExceptionMessage('verdict');

        (new StructuredOutputParser())->parse(json_encode([
            'reply_text' => 'hello',
            'verdict' => 'maybe-continue-idk',
        ]));
    }
}
