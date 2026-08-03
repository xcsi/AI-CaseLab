<?php

namespace App\Discussion\Exceptions;

use RuntimeException;

/**
 * Thrown by ChainedLlmClient when every tier in the ordered fallback chain
 * raised LlmProviderUnavailableException — including a not-running Ollama
 * and rate-limited/unconfigured free tiers, with paid either exhausted or
 * never a candidate (docs/13-ai-discussion-engine-design.md §1.4.5).
 * DiscussionService (a later phase) catches this specifically and returns a
 * typed "unavailable" result rather than a generic failure — the controller
 * turns that into the explicit "AI Discussion is temporarily unavailable"
 * UI state (§11.5), never a silent escalation to a paid provider.
 */
class NoLlmProviderAvailableException extends RuntimeException
{
}
