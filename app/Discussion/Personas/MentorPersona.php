<?php

namespace App\Discussion\Personas;

use App\Discussion\Contracts\AiPersonaInterface;
use App\Models\DiscussionSession;

/**
 * Student Mode (docs/13-ai-discussion-engine-design.md §3, §3.1) — a mentor
 * that guides without giving away the solution, and may offer a soft nudge
 * after a student stalls with no progress. Everything about this persona
 * that's plain data lives in config/discussion_personas.php; this class
 * exists only for the one piece of real behavior a mentor has that an
 * interviewer doesn't (shouldOfferHint()).
 */
class MentorPersona implements AiPersonaInterface
{
    public function systemPromptFragment(): string
    {
        $config = config('discussion_personas.mentor');

        return sprintf(
            'You are acting as a Mentor Review persona: %s in tone. '
            .'Hold the student to this acceptance bar before accepting their reasoning: %s. '
            .'If the student has made no real progress after several rounds, you may offer one gentle, '
            .'indirect nudge — never state the root cause or fix outright.',
            implode(', ', $config['tone_directives']),
            $config['acceptance_bar'],
        );
    }

    public function shouldOfferHint(DiscussionSession $session): bool
    {
        $config = config('discussion_personas.mentor');

        return $config['allow_hints'] && $session->round_count >= $config['stall_threshold'];
    }

    public function acceptanceBar(): string
    {
        return config('discussion_personas.mentor.acceptance_bar');
    }
}
