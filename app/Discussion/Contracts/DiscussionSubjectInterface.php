<?php

namespace App\Discussion\Contracts;

/**
 * The "what" half of the Subject × Persona split
 * (docs/13-ai-discussion-engine-design.md §1.5) — the material under review
 * and the ground truth the AI judges it against. CaseAttemptDiscussionSubject
 * (a later phase) is the only implementation Version 2 ships, adapting a
 * CaseAttempt into this shape; a future subject (a code submission, a design
 * brief, an open-ended topic) is a new implementation of this same interface,
 * not a change to DiscussionService or the core state machine.
 *
 * Deliberately narrow, read-only, and free of side effects — see §1.5 for
 * why "accepted" side effects (e.g. pre-filling a diagnosis) are handled via
 * the DiscussionAccepted event instead of a method here.
 */
interface DiscussionSubjectInterface
{
    /**
     * The role-framing sentence for the system prompt — e.g. "reviewing an
     * incident investigation" for a CaseAttempt (§4.1 point 1).
     */
    public function framingText(): string;

    /**
     * Server-side-only material the AI judges reasoning against — never sent
     * to the client, and the persona is instructed never to recite it
     * verbatim to the student (§4.1 point 3). May be thin or empty for a
     * subject with no single correct answer (§1.5).
     */
    public function groundTruthContext(): string;

    /**
     * What the student has done so far, worth grounding follow-up questions
     * in — e.g. evidence viewed and notebook content for a CaseAttempt
     * (§4.1 point 4). May be empty for a subject with nothing to track yet.
     */
    public function progressContext(): string;
}
