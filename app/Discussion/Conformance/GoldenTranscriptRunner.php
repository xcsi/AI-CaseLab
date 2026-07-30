<?php

namespace App\Discussion\Conformance;

use App\Discussion\Contracts\AiPersonaInterface;
use App\Discussion\Contracts\DiscussionSubjectInterface;
use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Support\LeakageGuard;
use App\Discussion\Support\SystemPromptBuilder;

/**
 * Runs one GoldenTranscript through a real LlmClientInterface exactly
 * once (docs/13-ai-discussion-engine-design.md §15.4/§15.6) — the same
 * seam DiscussionService uses in production (complete(), given a system
 * prompt + conversation history + newest message), so this exercises the
 * genuine request shape a real session sends, not a parallel path.
 *
 * Deliberately does not persist anything and knows nothing about
 * DiscussionSession/DiscussionTurn — this is a validation tool, not a
 * student-facing flow.
 */
final class GoldenTranscriptRunner
{
    public function __construct(
        private readonly SystemPromptBuilder $systemPromptBuilder = new SystemPromptBuilder(),
        private readonly LeakageGuard $leakageGuard = new LeakageGuard(),
    ) {}

    public function run(
        LlmClientInterface $client,
        AiPersonaInterface $persona,
        DiscussionSubjectInterface $subject,
        GoldenTranscript $transcript,
        string $sensitiveText,
    ): TranscriptRunResult {
        $systemPrompt = $this->systemPromptBuilder->build($persona, $subject);

        $history = [];
        $turnResults = [];

        foreach ($transcript->turns as $turn) {
            $result = $client->complete($systemPrompt, $history, $turn->studentMessage);

            $turnResults[] = new TranscriptTurnResult(
                studentMessage: $turn->studentMessage,
                replyText: $result->replyText,
                verdict: $result->verdict,
                expectedVerdict: $turn->expectedVerdict,
                graded: $turn->graded,
                leaked: $this->leakageGuard->containsLeak($result->replyText, $sensitiveText),
            );

            $history[] = ['role' => 'student', 'content' => $turn->studentMessage];
            $history[] = ['role' => 'ai', 'content' => $result->replyText];
        }

        return new TranscriptRunResult($turnResults);
    }
}
