<?php

namespace App\Discussion;

use App\Enums\DiscussionVerdict;

/**
 * The typed response Contracts\LlmClientInterface::complete() returns —
 * everything DiscussionService needs to persist one discussion_turns row
 * without knowing which provider/model actually answered, per
 * docs/13-ai-discussion-engine-design.md §10.1's exact field list.
 */
final class LlmTurnResult
{
    /**
     * @param  array<int, int>|null  $evidenceReferenced  Evidence-item IDs the reply referenced
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
    ) {}
}
