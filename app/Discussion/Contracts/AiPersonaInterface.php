<?php

namespace App\Discussion\Contracts;

use App\Models\DiscussionSession;

/**
 * The "who / how" half of the Subject × Persona split
 * (docs/13-ai-discussion-engine-design.md §1.5, §3) — tone, strictness, and
 * hint policy for one persona (Mentor, Interviewer, and later Security
 * Review, System Design Interview, Code Review, Architecture Review, DevOps
 * Review). Everything else about a persona is config data (§3.1), consumed
 * by SystemPromptBuilder; only the behavior that can't be expressed as plain
 * config lives here.
 */
interface AiPersonaInterface
{
    /**
     * The persona-specific directives folded into the assembled system
     * prompt (tone, strictness, acceptance bar, hint policy — §4.1 point 2).
     */
    public function systemPromptFragment(): string;

    /**
     * Whether this persona offers a soft nudge given the session's current
     * progress (e.g. Mentor's stall_threshold, §3.1) — never true for a
     * persona whose acceptance bar forbids hinting (e.g. Interviewer).
     */
    public function shouldOfferHint(DiscussionSession $session): bool;

    /**
     * What this persona requires before accepting a student's reasoning —
     * distinct from the subject's ground truth (Contracts\DiscussionSubjectInterface),
     * which is *what's* correct; this is *how rigorously* it must be shown.
     */
    public function acceptanceBar(): string;
}
