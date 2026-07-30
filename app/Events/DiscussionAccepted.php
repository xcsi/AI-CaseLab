<?php

namespace App\Events;

use App\Models\DiscussionSession;

/**
 * Fired by DiscussionService when a session transitions to Accepted
 * (docs/13-ai-discussion-engine-design.md §1.5, §2.2) — deliberately
 * subject-agnostic: carries only the DiscussionSession itself, never a
 * CaseAttempt or any other subject-specific type. DiscussionService knows
 * nothing about what (if anything) listens for this event; a future
 * subject type fires and consumes the exact same event with no change
 * here.
 */
class DiscussionAccepted
{
    public function __construct(
        public readonly DiscussionSession $session,
    ) {}
}
