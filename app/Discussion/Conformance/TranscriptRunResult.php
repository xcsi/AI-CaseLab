<?php

namespace App\Discussion\Conformance;

/**
 * The outcome of running one GoldenTranscript through a provider/model
 * exactly once. §15.6 runs each transcript 3 times and aggregates across
 * runs (ValidateDiscussionProvider does that aggregation) — this class is
 * a single run's result only.
 */
final class TranscriptRunResult
{
    /**
     * @param  array<int, TranscriptTurnResult>  $turnResults
     */
    public function __construct(
        public readonly array $turnResults,
    ) {}

    /**
     * §15.3: any leak, anywhere in the run, is an automatic fail —
     * checked and reported separately from verdict matching, never
     * averaged against it.
     */
    public function hasForbiddenViolation(): bool
    {
        foreach ($this->turnResults as $turnResult) {
            if ($turnResult->leaked) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every graded turn produced its expected verdict. Combined with the
     * absence of a forbidden violation, this is what makes one run count
     * as a "pass" toward §15.6's 2-of-3 threshold.
     */
    public function allGradedTurnsMatched(): bool
    {
        foreach ($this->turnResults as $turnResult) {
            if (! $turnResult->verdictMatched()) {
                return false;
            }
        }

        return true;
    }

    public function passed(): bool
    {
        return $this->allGradedTurnsMatched() && ! $this->hasForbiddenViolation();
    }
}
