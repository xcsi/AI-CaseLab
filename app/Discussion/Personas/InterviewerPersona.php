<?php

namespace App\Discussion\Personas;

use App\Discussion\Contracts\AiPersonaInterface;
use App\Models\DiscussionSession;

/**
 * Interview Mode (docs/13-ai-discussion-engine-design.md §3, §3.1) — a
 * strict interviewer that challenges aggressively, with no hints and no
 * teaching, ever. shouldOfferHint() is trivially always false here — that
 * triviality is itself the correct implementation of "Never" from §3's
 * persona comparison table, not a stub waiting to be filled in.
 */
class InterviewerPersona implements AiPersonaInterface
{
    public function systemPromptFragment(): string
    {
        $config = config('discussion_personas.interviewer');

        return sprintf(
            'You are acting as a Technical Interview persona: %s in tone. '
            .'Hold the candidate to this acceptance bar before accepting their reasoning: %s. '
            .'Do not offer hints or teach under any circumstance — this is a rigorous, no-assistance '
            .'interview. Being rigorous means being blunt about the reasoning, never about the person.',
            implode(', ', $config['tone_directives']),
            $config['acceptance_bar'],
        );
    }

    public function shouldOfferHint(DiscussionSession $session): bool
    {
        return false;
    }

    public function acceptanceBar(): string
    {
        return config('discussion_personas.interviewer.acceptance_bar');
    }
}
