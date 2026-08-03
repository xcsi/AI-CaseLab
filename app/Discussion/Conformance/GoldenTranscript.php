<?php

namespace App\Discussion\Conformance;

/**
 * One of §15.4's four fixed, versioned reference transcripts — a scripted
 * student conversation plus the persona it must be run under and the
 * per-turn expectations GoldenTranscriptRunner checks. `checksLeakage`
 * always applies (§15.3 forbidden-behavior 1 is zero-tolerance across
 * every transcript, not only transcript C, which is the one that
 * deliberately tries to provoke it).
 */
final class GoldenTranscript
{
    /**
     * @param  array<int, GoldenTranscriptTurn>  $turns
     */
    public function __construct(
        public readonly string $name,
        public readonly string $persona,
        public readonly array $turns,
    ) {}
}
