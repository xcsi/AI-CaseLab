<?php

namespace App\Discussion\Testing;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;
use RuntimeException;
use Throwable;

/**
 * The LlmClientInterface implementation bound in the testing environment
 * (docs/13-ai-discussion-engine-design.md §10.1) — scripted responses only,
 * zero network calls, ever. This is what keeps the automated suite fast and
 * deterministic through every later Discussion Engine phase, the same
 * testing philosophy the Repository interfaces already established for
 * Version 1.
 *
 * Usage in a test: queue exactly the LlmTurnResult(s) (willReturn()) or
 * exceptions (willThrow() — added in Phase 14 Milestone 4 so ChainedLlmClient's
 * multi-tier failure scenarios are scriptable) a scenario needs, let the
 * code under test call complete() as many times as it will, then assert on
 * recordedCalls() if the test cares what was actually sent.
 */
class FakeLlmClient implements LlmClientInterface
{
    /** @var array<int, LlmTurnResult|Throwable> */
    private array $queuedResponses = [];

    /** @var array<int, array{systemPrompt: SystemPrompt, conversationHistory: array<int, array{role: string, content: string}>, newMessage: string}> */
    private array $recordedCalls = [];

    public function willReturn(LlmTurnResult $result): static
    {
        $this->queuedResponses[] = $result;

        return $this;
    }

    public function willThrow(Throwable $exception): static
    {
        $this->queuedResponses[] = $exception;

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
                .'the test scenario needs one more willReturn()/willThrow() call than it has.'
            );
        }

        $next = array_shift($this->queuedResponses);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
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
