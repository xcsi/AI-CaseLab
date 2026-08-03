<?php

namespace App\Discussion\Support;

use App\Discussion\Contracts\AiPersonaInterface;
use App\Discussion\Contracts\DiscussionSubjectInterface;
use App\Discussion\SystemPrompt;

/**
 * Assembles persona + subject into the system prompt sent on every turn of
 * a session (docs/13-ai-discussion-engine-design.md §4.1) — pure string
 * composition from what AiPersonaInterface/DiscussionSubjectInterface
 * already supply, no business logic, no decisions.
 *
 * The conversation transcript and the newest student message are sent
 * alongside this builder's output directly to LlmClientInterface::complete()
 * (§4.2's "the cached system prompt + the full conversation transcript so
 * far + the newest student message" — three things sent together at call
 * time by whoever orchestrates the turn) — they are not inputs this class
 * transforms, matching §1.1's description of this class exactly ("assembles
 * persona + case context into the system prompt").
 */
class SystemPromptBuilder
{
    public function build(AiPersonaInterface $persona, DiscussionSubjectInterface $subject): SystemPrompt
    {
        $text = implode("\n\n", [
            $this->roleSection($subject),
            $persona->systemPromptFragment(),
            $this->groundTruthSection($subject),
            $this->progressSection($subject),
            $this->outputContractSection(),
        ]);

        return new SystemPrompt($text);
    }

    /**
     * §4.1 point 1 — the subject supplies the "what"; the persona fragment
     * immediately after supplies the "who/how".
     */
    private function roleSection(DiscussionSubjectInterface $subject): string
    {
        return 'Role: You are '.$subject->framingText().'.';
    }

    /**
     * §4.1 point 3 — server-side only, for the model's judgment. The
     * instruction never to recite this verbatim lives in the persona's own
     * fragment (§3's "never state the root cause or fix outright" /
     * "do not offer hints"), not duplicated here.
     */
    private function groundTruthSection(DiscussionSubjectInterface $subject): string
    {
        return "Ground truth (for your judgment only):\n".$subject->groundTruthContext();
    }

    /** §4.1 point 4. */
    private function progressSection(DiscussionSubjectInterface $subject): string
    {
        return "What the student has done so far:\n".$subject->progressContext();
    }

    /**
     * §4.1 point 5 / §4.3 — the structured-output contract every persona's
     * reply must follow, independent of which persona or subject is in
     * play.
     */
    private function outputContractSection(): string
    {
        return 'Output contract: respond with only a JSON object matching this shape — '
            .'{"reply_text": string, "verdict": "continue"|"accept"|"end_unresolved", '
            .'"evidence_gap_detected": boolean, "contradiction_detected": boolean, '
            .'"internal_note": string}. No prose outside the JSON object.';
    }
}
