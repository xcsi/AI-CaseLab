<?php

namespace App\Discussion\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by LlmClientFactory when the application owner has explicitly
 * enabled paid fallback (LLM_ALLOW_PAID_FALLBACK=true) but named a paid
 * provider config doesn't recognize ("openai" or "anthropic" — pick one,
 * per docs/13-ai-discussion-engine-design.md §1.4.4). A deliberate config
 * mistake here fails loudly and immediately rather than silently building a
 * chain with no paid tier despite the owner's explicit intent — the
 * opposite failure mode from the safety this section otherwise guarantees,
 * so it gets its own clear error instead of being swallowed.
 */
class InvalidLlmConfigurationException extends InvalidArgumentException
{
}
