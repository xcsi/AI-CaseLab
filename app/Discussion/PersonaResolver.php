<?php

namespace App\Discussion;

use App\Discussion\Contracts\AiPersonaInterface;
use App\Discussion\Exceptions\UnknownPersonaException;
use App\Discussion\Personas\InterviewerPersona;
use App\Discussion\Personas\MentorPersona;

/**
 * Factory: persona key -> AiPersonaInterface instance. Mirrors
 * EvaluationStrategyResolver's shape (app/Evaluation/EvaluationStrategyResolver.php)
 * — the only place that knows which concrete persona class answers to which
 * key. Version 2 ships exactly the two cases below; a future persona that
 * needs its own class (docs/13-ai-discussion-engine-design.md §3.1) is a
 * new constructor dependency and match arm here, nothing else in the
 * Discussion module changes.
 */
class PersonaResolver
{
    public function __construct(
        private readonly MentorPersona $mentorPersona,
        private readonly InterviewerPersona $interviewerPersona,
    ) {}

    public function resolve(string $persona): AiPersonaInterface
    {
        return match ($persona) {
            'mentor' => $this->mentorPersona,
            'interviewer' => $this->interviewerPersona,
            default => throw new UnknownPersonaException("Unknown persona: \"{$persona}\"."),
        };
    }
}
