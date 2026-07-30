<?php

namespace App\Discussion\Contracts;

use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;

/**
 * The one boundary DiscussionService depends on for talking to a language
 * model — provider-agnostic by construction, per
 * docs/13-ai-discussion-engine-design.md §1.4/§10.1. DiscussionService never
 * knows, and must never come to know, which concrete implementation answered
 * a given call: a single provider client, or the ordered fallback chain
 * composed from several (§1.4.2), are equally valid implementations of this
 * same interface (Liskov).
 */
interface LlmClientInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $conversationHistory  Prior turns, oldest first
     */
    public function complete(SystemPrompt $systemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult;
}
