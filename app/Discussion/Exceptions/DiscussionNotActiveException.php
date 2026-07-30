<?php

namespace App\Discussion\Exceptions;

use RuntimeException;

/**
 * Thrown by DiscussionService::respond()/end() when the session has already
 * reached a terminal state (Accepted, EndedByStudent, or MaxRoundsReached) —
 * the state machine (docs/13-ai-discussion-engine-design.md §2.1) only
 * accepts turns/end requests while a session is Active.
 */
class DiscussionNotActiveException extends RuntimeException
{
}
