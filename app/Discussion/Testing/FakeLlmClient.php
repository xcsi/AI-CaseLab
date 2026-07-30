<?php

namespace App\Discussion\Testing;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;
use RuntimeException;

/**
 * The LlmClientInterface implementation bound in the testing environment
 * (docs/13-ai-discussion-engine-design.md §10.1) — scripted responses only,
 * zero network calls, ever. This is what keeps the automated suite fast and
 * deterministic through every later Discussion Engine phase, the same
 * testing philosophy the Repository interfaces already established for
 * Version 1.
 *
 * Usage in a test: queue exactly the LlmTurnResult(s) a scenario needs via
 * willReturn(), let the code under test call complete() as many times as it
 * will, then assert on recordedCalls() if the test cares what was actually
 * sent.
 */
class FakeLlmClient implements LlmClientInterface
{
    /** @var array<int, LlmTurnResult> */
    private array $queuedResponses = [];

    /** @var array<int, array{systemPrompt: SystemPrompt, conversationHistory: array<int, array{role: string, content: string}>, newMessage: string}> */
    private array $recordedCalls = [];

    public function willReturn(LlmTurnResult $result): static
    {
        $this->queuedResponses[] = $result;

        return $this;
    }

    public function complete(SystemPrompt $systemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult
    {
        $this->recordedCalls[] = [
            'systemPrompt' => $systemPrompt,
            'conversationHistory' => $conversationHistory,
            'newMessage' => $newMessage,
        ];

        if ($this->queuedResponses === []) {
            throw new RuntimeException(
                'FakeLlmClient::complete() was called with no scripted response queued — '
                .'the test scenario needs one more willReturn() call than it has.'
            );
        }

        return array_shift($this->queuedResponses);
    }

    /**
     * @return array<int, array{systemPrompt: SystemPrompt, conversationHistory: array<int, array{role: string, content: string}>, newMessage: string}>
     */
    public function recordedCalls(): array
    {
        return $this->recordedCalls;
    }

    public function callCount(): int
    {
        return count($this->recordedCalls);
    }
}
