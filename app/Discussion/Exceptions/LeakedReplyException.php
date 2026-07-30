<?php

namespace App\Discussion\Exceptions;

use RuntimeException;

/**
 * Thrown by DiscussionService when LeakageGuard flags an AI reply as
 * leaking the model solution verbatim or near-verbatim
 * (docs/13-ai-discussion-engine-design.md §1.3, §8). The turn is never
 * persisted, and — because the check runs inside the same database
 * transaction as the rest of the turn — the student's own message for this
 * exchange is rolled back too, leaving the session exactly as it was before
 * the attempt. Stops immediately; does not retry or regenerate.
 */
class LeakedReplyException extends RuntimeException
{
}
