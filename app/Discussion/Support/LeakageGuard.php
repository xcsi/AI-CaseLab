<?php

namespace App\Discussion\Support;

/**
 * A second, non-LLM check that an AI reply doesn't leak the model solution
 * verbatim or near-verbatim, independent of whatever the system prompt
 * instructed the model to do (docs/13-ai-discussion-engine-design.md §1.3,
 * §8) — so a successfully-injected or simply careless model doesn't get the
 * last word. Detection only: what happens to a reply that leaks (reject,
 * regenerate, degrade) is the caller's decision (DiscussionService, a later
 * phase), not this class's.
 */
final class LeakageGuard
{
    /**
     * A leaked chunk shorter than this many words is indistinguishable from
     * ordinary shared vocabulary (persona/domain phrasing both parties would
     * naturally use) — flagging it would produce false positives on
     * legitimate replies. Long enough to be evidence of actual copying,
     * short enough to catch a leak that doesn't repeat the whole passage.
     */
    private const NEAR_VERBATIM_WINDOW_WORDS = 6;

    public function containsLeak(string $replyText, string $sensitiveText): bool
    {
        $normalizedSensitive = $this->normalize($sensitiveText);

        if ($normalizedSensitive === '') {
            return false;
        }

        $normalizedReply = $this->normalize($replyText);

        return str_contains($normalizedReply, $normalizedSensitive)
            || $this->containsNearVerbatimChunk($normalizedReply, $normalizedSensitive);
    }

    private function normalize(string $text): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($text)));
    }

    private function containsNearVerbatimChunk(string $normalizedReply, string $normalizedSensitive): bool
    {
        $words = explode(' ', $normalizedSensitive);

        if (count($words) < self::NEAR_VERBATIM_WINDOW_WORDS) {
            // Too short for a meaningful chunk check narrower than the
            // verbatim check above already covers.
            return false;
        }

        for ($i = 0; $i <= count($words) - self::NEAR_VERBATIM_WINDOW_WORDS; $i++) {
            $chunk = implode(' ', array_slice($words, $i, self::NEAR_VERBATIM_WINDOW_WORDS));

            if (str_contains($normalizedReply, $chunk)) {
                return true;
            }
        }

        return false;
    }
}
