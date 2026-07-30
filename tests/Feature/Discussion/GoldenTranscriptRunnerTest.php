<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Conformance\GoldenTranscript;
use App\Discussion\Conformance\GoldenTranscriptRunner;
use App\Discussion\Conformance\GoldenTranscriptTurn;
use App\Discussion\Contracts\DiscussionSubjectInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\Personas\MentorPersona;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use Tests\TestCase;

/**
 * Proves GoldenTranscriptRunner's pass/fail logic for Phase 21 Milestone
 * 1, per docs/13-ai-discussion-engine-design.md §15.4/§15.6 — entirely
 * against FakeLlmClient, zero real network calls, since this is the
 * harness's own correctness, not a real provider's behavior (that's
 * Milestones 2-4, run manually).
 */
class GoldenTranscriptRunnerTest extends TestCase
{
    public function test_a_run_where_every_graded_turn_matches_and_nothing_leaks_passes(): void
    {
        $client = new FakeLlmClient();
        $client->willReturn(new LlmTurnResult('Continue reply.', DiscussionVerdict::Continue));
        $client->willReturn(new LlmTurnResult('Accept reply.', DiscussionVerdict::Accept));

        $transcript = new GoldenTranscript('Test transcript', 'mentor', [
            new GoldenTranscriptTurn('First student message.', DiscussionVerdict::Continue),
            new GoldenTranscriptTurn('Second student message.', DiscussionVerdict::Accept),
        ]);

        $result = $this->runner()->run($client, new MentorPersona(), $this->subject(), $transcript, 'the sensitive answer text');

        $this->assertTrue($result->passed());
        $this->assertTrue($result->allGradedTurnsMatched());
        $this->assertFalse($result->hasForbiddenViolation());
    }

    public function test_a_mismatched_graded_verdict_fails_the_run(): void
    {
        $client = new FakeLlmClient();
        // Transcript expects Accept but the provider returns Continue —
        // exactly transcript B's sycophancy-resistance failure shape,
        // just inverted for this test (expects the wrong thing on purpose).
        $client->willReturn(new LlmTurnResult('Reply.', DiscussionVerdict::Continue));

        $transcript = new GoldenTranscript('Test transcript', 'mentor', [
            new GoldenTranscriptTurn('Student message.', DiscussionVerdict::Accept),
        ]);

        $result = $this->runner()->run($client, new MentorPersona(), $this->subject(), $transcript, 'the sensitive answer text');

        $this->assertFalse($result->passed());
        $this->assertFalse($result->allGradedTurnsMatched());
        $this->assertFalse($result->hasForbiddenViolation());
    }

    public function test_an_ungraded_turns_verdict_never_affects_the_result(): void
    {
        $client = new FakeLlmClient();
        // An ungraded turn returning a verdict that would never match any
        // expectation (there is none to match) must not fail the run —
        // exactly transcript D's first, setup-only turn.
        $client->willReturn(new LlmTurnResult('Setup reply.', DiscussionVerdict::EndUnresolved));
        $client->willReturn(new LlmTurnResult('Graded reply.', DiscussionVerdict::Continue));

        $transcript = new GoldenTranscript('Test transcript', 'mentor', [
            new GoldenTranscriptTurn('Setup message.', graded: false),
            new GoldenTranscriptTurn('Graded message.', DiscussionVerdict::Continue),
        ]);

        $result = $this->runner()->run($client, new MentorPersona(), $this->subject(), $transcript, 'the sensitive answer text');

        $this->assertTrue($result->passed());
    }

    public function test_a_leaked_reply_fails_the_run_even_when_the_verdict_matched(): void
    {
        $client = new FakeLlmClient();
        $client->willReturn(new LlmTurnResult(
            'Well, actually the sensitive answer text is exactly what happened here.',
            DiscussionVerdict::Continue,
        ));

        $transcript = new GoldenTranscript('Test transcript', 'mentor', [
            new GoldenTranscriptTurn('Student message.', DiscussionVerdict::Continue),
        ]);

        $result = $this->runner()->run($client, new MentorPersona(), $this->subject(), $transcript, 'the sensitive answer text');

        // The graded verdict itself matched...
        $this->assertTrue($result->allGradedTurnsMatched());
        // ...but §15.3's zero-tolerance leakage rule still fails the run.
        $this->assertTrue($result->hasForbiddenViolation());
        $this->assertFalse($result->passed());
    }

    public function test_the_conversation_history_accumulates_turn_by_turn(): void
    {
        $client = new FakeLlmClient();
        $client->willReturn(new LlmTurnResult('First reply.', DiscussionVerdict::Continue));
        $client->willReturn(new LlmTurnResult('Second reply.', DiscussionVerdict::Continue));

        $transcript = new GoldenTranscript('Test transcript', 'mentor', [
            new GoldenTranscriptTurn('First message.', DiscussionVerdict::Continue),
            new GoldenTranscriptTurn('Second message.', DiscussionVerdict::Continue),
        ]);

        $this->runner()->run($client, new MentorPersona(), $this->subject(), $transcript, '');

        $calls = $client->recordedCalls();
        $this->assertCount(2, $calls);
        $this->assertSame([], $calls[0]['conversationHistory']);
        $this->assertSame(
            [
                ['role' => 'student', 'content' => 'First message.'],
                ['role' => 'ai', 'content' => 'First reply.'],
            ],
            $calls[1]['conversationHistory']
        );
        $this->assertSame('Second message.', $calls[1]['newMessage']);
    }

    private function runner(): GoldenTranscriptRunner
    {
        return new GoldenTranscriptRunner();
    }

    private function subject(): DiscussionSubjectInterface
    {
        return new class implements DiscussionSubjectInterface
        {
            public function framingText(): string
            {
                return 'reviewing a test scenario';
            }

            public function groundTruthContext(): string
            {
                return 'Ground truth for the test scenario.';
            }

            public function progressContext(): string
            {
                return 'No progress yet.';
            }
        };
    }
}
