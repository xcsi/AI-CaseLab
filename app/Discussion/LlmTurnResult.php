<?php

namespace App\Discussion;

use App\Enums\DiscussionVerdict;

/**
 * The typed response Contracts\LlmClientInterface::complete() returns —
 * everything DiscussionService needs to persist one discussion_turns row
 * without knowing which provider/model actually answered, per
 * docs/13-ai-discussion-engine-design.md §10.1's exact field list, plus
 * `fallbackLog` (§9.1's `discussion_turns.fallback_log`, wired end-to-end
 * in Phase 20 Milestone 1 — ChainedLlmClient is the only thing that
 * populates it, on the exact fallback path §9.1 describes: "populated
 * only when the primary tier didn't serve the turn").
 */
final class LlmTurnResult
{
    /**
     * @param  array<int, int>|null  $evidenceReferenced  Evidence-item IDs the reply referenced
     * @param  array<int, array{tier: string, result: string}>|null  $fallbackLog  Earlier tiers attempted and skipped before this one answered
     */
    public function __construct(
        public readonly string $replyText,
        public readonly DiscussionVerdict $verdict,
        public readonly ?array $evidenceReferenced = null,
        public readonly ?string $internalNote = null,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?array $fallbackLog = null,
    ) {}
}
