<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Models\User;

/**
 * docs/13-ai-discussion-engine-design.md §10/§8: "owner + admin/instructor
 * only" — the same access boundary Diagnosis already has. Note on "mirrors
 * CaseAttemptPolicy": that class doesn't actually exist in this codebase —
 * CaseAttempt ownership is enforced by EnsureAttemptBelongsToUser
 * middleware instead (`$attempt->user_id === $request->user()->id`), not a
 * Policy. This class reuses that exact same underlying ownership field via
 * the session's discussable relation rather than inventing a parallel
 * ownership rule, which is what "do not duplicate ownership logic" means
 * here in practice.
 */
class DiscussionSessionPolicy
{
    /**
     * Owner, or an admin/instructor reviewing the transcript.
     */
    public function view(User $user, DiscussionSession $session): bool
    {
        return $this->isOwner($user, $session)
            || $user->hasRole(UserRole::Admin)
            || $user->hasRole(UserRole::Instructor);
    }

    /**
     * Only the owning student may post messages into a session —
     * admin/instructor can review (view), not converse as the student.
     */
    public function participate(User $user, DiscussionSession $session): bool
    {
        return $this->isOwner($user, $session);
    }

    private function isOwner(User $user, DiscussionSession $session): bool
    {
        return $session->discussable instanceof CaseAttempt
            && $session->discussable->user_id === $user->id;
    }
}
