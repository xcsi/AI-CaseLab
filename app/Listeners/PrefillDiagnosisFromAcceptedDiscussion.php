<?php

namespace App\Listeners;

use App\Enums\DiscussionTurnRole;
use App\Events\DiscussionAccepted;
use App\Models\CaseAttempt;

/**
 * The one piece of CaseAttempt-specific behavior a discussion's acceptance
 * causes — deliberately kept out of DiscussionService entirely
 * (docs/13-ai-discussion-engine-design.md §1.5, §2.2). Auto-discovered by
 * Laravel from this handle() method's DiscussionAccepted type-hint, the
 * same convention RecordEvidenceView already uses for EvidenceViewed — no
 * explicit registration needed.
 *
 * Important constraint this class respects, found by reading
 * DiagnosisSubmissionService before writing this: submit() is idempotent
 * purely on a diagnosis row *existing* for the attempt — "if ($existing) {
 * return $existing; }", with no draft/submitted distinction. Creating a
 * Diagnosis row here (even an unsubmitted-looking one) would make the
 * student's real, later submission silently return that stale row instead
 * of persisting what they actually wrote — a serious Version 1 regression.
 * So this listener writes nothing to the `diagnoses` table. It populates
 * `discussion_sessions.outcome_summary` instead — already-existing schema
 * (§9.1: "Short instructor-facing recap, generated on session close"),
 * exactly the field this milestone's job fits into without inventing
 * anything. A future Diagnosis Submission form (Phase 18) reads this (or
 * the session's turns directly) to pre-fill its inputs at render time, the
 * same way the existing form already pre-checks cited-evidence chips from
 * EvidenceView at render time rather than writing that state in advance.
 */
class PrefillDiagnosisFromAcceptedDiscussion
{
    public function handle(DiscussionAccepted $event): void
    {
        $session = $event->session;

        if ($session->discussable_type !== CaseAttempt::class) {
            return;
        }

        $finalStudentTurn = $session->turns()
            ->where('role', DiscussionTurnRole::Student->value)
            ->orderByDesc('sequence_order')
            ->first();

        $session->update([
            'outcome_summary' => sprintf(
                'Accepted after %d round(s). Student\'s accepted position: %s',
                $session->round_count,
                $finalStudentTurn?->content ?? '(no student turn found)',
            ),
        ]);
    }
}
