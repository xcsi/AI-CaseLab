<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Support\LeakageGuard;
use Tests\TestCase;

/**
 * Proves LeakageGuard for the Phase 15 Milestone 5 catch-up, per
 * docs/13-ai-discussion-engine-design.md §1.3/§8: catches verbatim and
 * near-verbatim model-solution leakage, and passes clean replies through
 * unchanged (i.e. never flags them). No LLM call anywhere — pure string
 * comparison against fixture text.
 */
class LeakageGuardTest extends TestCase
{
    private const MODEL_SOLUTION = 'The checkout controller calls the payment gateway with no timeout '
        .'configured. Under load, the gateway response occasionally exceeds the default socket timeout, '
        .'and the resulting exception is never caught.';

    public function test_a_reply_that_repeats_the_model_solution_verbatim_is_flagged(): void
    {
        $reply = 'Great catch! '.self::MODEL_SOLUTION.' Does that match what you found?';

        $this->assertTrue((new LeakageGuard())->containsLeak($reply, self::MODEL_SOLUTION));
    }

    public function test_a_reply_that_is_verbatim_but_a_different_case_is_still_flagged(): void
    {
        $reply = strtoupper(self::MODEL_SOLUTION);

        $this->assertTrue((new LeakageGuard())->containsLeak($reply, self::MODEL_SOLUTION));
    }

    public function test_a_reply_that_repeats_a_significant_chunk_verbatim_is_flagged_as_near_verbatim(): void
    {
        // The AI paraphrases most of it but copy-pastes one six-plus-word
        // chunk straight from the middle of the model solution.
        $reply = 'Think about what happens when "the gateway response occasionally exceeds the default '
            .'socket timeout" — what would you expect to see in the logs?';

        $this->assertTrue((new LeakageGuard())->containsLeak($reply, self::MODEL_SOLUTION));
    }

    public function test_a_reply_with_extra_whitespace_and_line_breaks_around_the_leaked_text_is_still_flagged(): void
    {
        $reply = "Well,\n\n  ".self::MODEL_SOLUTION."   \nis that what you're seeing?";

        $this->assertTrue((new LeakageGuard())->containsLeak($reply, self::MODEL_SOLUTION));
    }

    public function test_a_clean_reply_that_shares_only_ordinary_short_phrases_is_not_flagged(): void
    {
        // Genuinely Socratic, doesn't repeat any substantial chunk of the
        // model solution — shares only short, unavoidable domain phrasing
        // ("payment gateway", "timeout") that legitimate replies will
        // naturally use too.
        $reply = 'What does the log say about the payment gateway right before the error? '
            .'Is there anything about a timeout in there?';

        $this->assertFalse((new LeakageGuard())->containsLeak($reply, self::MODEL_SOLUTION));
    }

    public function test_a_reply_discussing_a_completely_unrelated_topic_is_not_flagged(): void
    {
        $reply = 'Have you looked at the database snapshot yet? What does the index list show?';

        $this->assertFalse((new LeakageGuard())->containsLeak($reply, self::MODEL_SOLUTION));
    }

    public function test_an_empty_sensitive_text_never_flags_anything(): void
    {
        // Mirrors CaseAttemptDiscussionSubject's "(no model solution
        // recorded for this case)" state — nothing sensitive to compare
        // against, so nothing should ever be flagged.
        $this->assertFalse((new LeakageGuard())->containsLeak('Any reply at all.', ''));
    }

    public function test_a_short_sensitive_text_below_the_chunk_window_still_catches_a_verbatim_match(): void
    {
        $this->assertTrue((new LeakageGuard())->containsLeak(
            'The root cause is a missing timeout.',
            'a missing timeout'
        ));
    }

    public function test_a_short_sensitive_text_below_the_chunk_window_does_not_false_positive_on_a_paraphrase(): void
    {
        $this->assertFalse((new LeakageGuard())->containsLeak(
            'What might be missing from that configuration?',
            'a missing timeout'
        ));
    }
}
