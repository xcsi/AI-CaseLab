<?php

namespace App\Discussion\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by PersonaResolver when a persona key doesn't match any registered
 * persona — the application-layer validation
 * docs/13-ai-discussion-engine-design.md §9.1 relies on to keep
 * discussion_sessions.persona a plain string instead of a DB enum.
 */
class UnknownPersonaException extends InvalidArgumentException
{
}
