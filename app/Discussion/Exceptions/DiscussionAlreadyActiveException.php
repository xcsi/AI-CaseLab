<?php

namespace App\Discussion\Exceptions;

use RuntimeException;

/**
 * Thrown by DiscussionService::start() when the subject already has an
 * Active session — "one active session per subject enforced at the service
 * layer" (docs/13-ai-discussion-engine-design.md §9.1).
 */
class DiscussionAlreadyActiveException extends RuntimeException
{
}
