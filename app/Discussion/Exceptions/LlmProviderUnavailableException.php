<?php

namespace App\Discussion\Exceptions;

use RuntimeException;

/**
 * Thrown by an LlmClientInterface implementation when that specific tier
 * can't serve any request right now — connection refused/timeout, rate
 * limited, quota exhausted, or otherwise temporarily unavailable
 * (docs/13-ai-discussion-engine-design.md §1.4.3). ChainedLlmClient (Phase
 * 14 Milestone 4) catches this specifically and tries the next tier;
 * anything else propagates as the genuine request-level error it is.
 *
 * Pulled forward from Milestone 4 into Milestone 2: OpenAiCompatibleLlmClient
 * needs something concrete to throw on a 429/timeout to be testable at all,
 * and this exception's shape doesn't depend on ChainedLlmClient existing —
 * flagged in the Milestone 2 report rather than silently reordered.
 */
class LlmProviderUnavailableException extends RuntimeException
{
}
