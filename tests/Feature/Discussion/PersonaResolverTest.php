<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\UnknownPersonaException;
use App\Discussion\Personas\InterviewerPersona;
use App\Discussion\Personas\MentorPersona;
use App\Discussion\PersonaResolver;
use Tests\TestCase;

/**
 * Proves PersonaResolver for Phase 15 Milestone 1 — the only place that
 * decides which concrete AiPersonaInterface answers to which key, per
 * docs/13-ai-discussion-engine-design.md §1.3, mirroring
 * EvaluationStrategyResolver's shape.
 */
class PersonaResolverTest extends TestCase
{
    public function test_it_resolves_mentor_to_mentor_persona(): void
    {
        $this->assertInstanceOf(
            MentorPersona::class,
            app(PersonaResolver::class)->resolve('mentor')
        );
    }

    public function test_it_resolves_interviewer_to_interviewer_persona(): void
    {
        $this->assertInstanceOf(
            InterviewerPersona::class,
            app(PersonaResolver::class)->resolve('interviewer')
        );
    }

    public function test_an_unknown_persona_key_throws_a_clear_exception(): void
    {
        $this->expectException(UnknownPersonaException::class);
        $this->expectExceptionMessage('security_review');

        app(PersonaResolver::class)->resolve('security_review');
    }
}
