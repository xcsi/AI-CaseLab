<?php

// Persona config for the Discussion Engine's "who / how" axis
// (docs/13-ai-discussion-engine-design.md §3.1). Version 2 ships exactly the
// two keys below; a third persona (Security Review, System Design
// Interview, Code Review, Architecture Review, DevOps Review — §3.1's named
// future direction) is a new key here plus, only if it needs behavior
// beyond tone, a small class implementing AiPersonaInterface — not a
// PersonaResolver/DiscussionService change.
//
// Config-driven from a PHP file for Version 2's launch, not a database
// table yet (§3.2) — two developer-defined personas don't yet justify an
// admin-facing CRUD screen.

return [

    'mentor' => [
        'display_name' => 'Mentor Review',
        'tone_directives' => ['encouraging', 'curious', 'collaborative'],
        'strictness' => 0.4,
        'allow_hints' => true,
        'stall_threshold' => 3,
        'default_max_rounds' => 8,
        'acceptance_bar' => 'directionally correct + at least one evidence citation',
    ],

    'interviewer' => [
        'display_name' => 'Technical Interview',
        'tone_directives' => ['terse', 'skeptical', 'evaluative'],
        'strictness' => 0.9,
        'allow_hints' => false,
        'stall_threshold' => null,
        'default_max_rounds' => 5,
        'acceptance_bar' => 'fully specified root cause + fix + all claims evidence-backed',
    ],

];
