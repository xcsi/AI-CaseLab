<?php

namespace App\Discussion;

/**
 * The assembled, provider-agnostic system prompt for one discussion session
 * (persona directives + subject framing/ground-truth/progress context, per
 * docs/13-ai-discussion-engine-design.md §4.1) — built once per session by
 * SystemPromptBuilder (a later phase) and passed into
 * Contracts\LlmClientInterface::complete() on every turn.
 */
final class SystemPrompt
{
    public function __construct(
        public readonly string $text,
    ) {}
}
