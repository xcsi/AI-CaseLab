<?php

namespace App\Discussion\Infrastructure\Llm;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;

/**
 * The ordered, cost-safe fallback chain — itself just another
 * LlmClientInterface implementation, a composite over several others
 * (docs/13-ai-discussion-engine-design.md §1.4.2). DiscussionService (a
 * later phase) depends only on LlmClientInterface and never knows this
 * chain exists, what order it tries tiers in, or how many tiers there are.
 *
 * This class knows nothing about "free," "paid," "Ollama," or any other
 * tier concept — it is a pure, generic "try these clients in this order"
 * mechanism. The cost-safety guarantee ("never silently reach a paid
 * provider unless explicitly enabled," §1.4.4) is enforced entirely by
 * *what LlmClientFactory (Phase 14 Milestone 5) puts into the array passed
 * here* — a paid client that was never constructed, never added to this
 * list, can never be reached by this class no matter what it does. That is
 * the whole point of keeping this class this dumb.
 */
class ChainedLlmClient implements LlmClientInterface
{
    /**
     * @param  array<int, array{name: string, client: LlmClientInterface}>  $tiers  In the exact order they should be tried
     */
    public function __construct(
        private readonly array $tiers,
    ) {}

    public function complete(SystemPrompt $systemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult
    {
        $skippedTiers = [];

        foreach ($this->tiers as $tier) {
            try {
                $result = $tier['client']->complete($systemPrompt, $conversationHistory, $newMessage);

                // fallback_log (§9.1) is "populated only when the primary
                // tier didn't serve the turn" — when nothing was skipped,
                // return the tier's own result untouched (same object,
                // same fallbackLog it already carried) rather than
                // wrapping every successful call in a new instance.
                if ($skippedTiers === []) {
                    return $result;
                }

                return new LlmTurnResult(
                    replyText: $result->replyText,
                    verdict: $result->verdict,
                    evidenceReferenced: $result->evidenceReferenced,
                    internalNote: $result->internalNote,
                    promptTokens: $result->promptTokens,
                    completionTokens: $result->completionTokens,
                    provider: $result->provider,
                    model: $result->model,
                    fallbackLog: $skippedTiers,
                );
            } catch (LlmProviderUnavailableException $e) {
                // This tier can't serve any request right now — try the
                // next one. Any other exception type is a genuine
                // request-level problem and is deliberately NOT caught
                // here, so it propagates immediately instead of wasting
                // every remaining tier on a failure that would happen
                // identically everywhere (§1.4.3).
                $skippedTiers[] = ['tier' => $tier['name'], 'result' => $e->getMessage()];
            }
        }

        throw new NoLlmProviderAvailableException(
            'No configured LLM provider tier could serve this request. Tried, in order: '
            .(implode(', ', array_column($skippedTiers, 'tier')) ?: '(no tiers configured)').'.'
        );
    }

    /**
     * Read-only introspection of this chain's composition, in order — never
     * exposes the underlying clients themselves. Exists for testing
     * LlmClientFactory's output (Phase 14 Milestone 5); not for anything
     * DiscussionService needs — it depends on LlmClientInterface alone and
     * has no reason to call this. Per-call fallback observability (Phase 20
     * Milestone 1) is carried on the returned LlmTurnResult itself
     * (`fallbackLog`), not through this method — this only ever describes
     * the chain's static configuration, not what happened on one request.
     *
     * @return array<int, string>
     */
    public function tierNames(): array
    {
        return array_column($this->tiers, 'name');
    }
}
