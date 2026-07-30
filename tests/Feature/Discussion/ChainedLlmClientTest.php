<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
use App\Discussion\Infrastructure\Llm\ChainedLlmClient;
use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use RuntimeException;
use Tests\TestCase;

/**
 * Proves ChainedLlmClient's ordered-fallback mechanism for Phase 14
 * Milestone 4, "the most important milestone of the provider layer" — every
 * scenario built entirely from FakeLlmClient instances (never a real
 * provider client), since this class's correctness has nothing to do with
 * any specific wire format; it's pure composition over LlmClientInterface.
 * Zero network calls, zero Http::fake() needed here at all.
 */
class ChainedLlmClientTest extends TestCase
{
    public function test_a_single_available_tier_serves_the_request_directly(): void
    {
        $tier1 = new FakeLlmClient();
        $result = new LlmTurnResult('reply', DiscussionVerdict::Continue);
        $tier1->willReturn($result);

        $chain = new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
        ]);

        $this->assertSame($result, $chain->complete(new SystemPrompt('system'), [], 'hello'));
    }

    public function test_it_falls_through_to_the_next_tier_only_on_llm_provider_unavailable_exception(): void
    {
        $tier1 = new FakeLlmClient();
        $tier1->willThrow(new LlmProviderUnavailableException('ollama unreachable'));

        $tier2 = new FakeLlmClient();
        $result = new LlmTurnResult('reply from tier 2', DiscussionVerdict::Continue);
        $tier2->willReturn($result);

        $chain = new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
            ['name' => 'openrouter_free', 'client' => $tier2],
        ]);

        $actual = $chain->complete(new SystemPrompt('system'), [], 'hello');

        // Not assertSame($result, $actual) — a fallback occurred, so
        // ChainedLlmClient wraps the tier's result in a new LlmTurnResult
        // carrying fallback_log (Phase 20 Milestone 1); the reply content
        // itself is unchanged.
        $this->assertSame($result->replyText, $actual->replyText);
        $this->assertSame($result->verdict, $actual->verdict);
        $this->assertSame(
            [['tier' => 'ollama', 'result' => 'ollama unreachable']],
            $actual->fallbackLog
        );
        // Deterministic order: tier 1 was actually tried (and failed)
        // before tier 2 was ever touched, not just "eventually returned".
        $this->assertSame(1, $tier1->callCount());
        $this->assertSame(1, $tier2->callCount());
    }

    public function test_it_tries_every_tier_in_the_exact_configured_order_before_exhausting(): void
    {
        $tier1 = new FakeLlmClient();
        $tier1->willThrow(new LlmProviderUnavailableException('ollama unreachable'));

        $tier2 = new FakeLlmClient();
        $tier2->willThrow(new LlmProviderUnavailableException('openrouter rate limited'));

        $tier3 = new FakeLlmClient();
        $result = new LlmTurnResult('reply from gemini', DiscussionVerdict::Continue);
        $tier3->willReturn($result);

        $chain = new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
            ['name' => 'openrouter_free', 'client' => $tier2],
            ['name' => 'gemini_free', 'client' => $tier3],
        ]);

        $actual = $chain->complete(new SystemPrompt('system'), [], 'hello');

        // Not assertSame($result, $actual) — two fallbacks occurred; see
        // the identical note in the two-tier fallback test above.
        $this->assertSame($result->replyText, $actual->replyText);
        $this->assertSame(
            [
                ['tier' => 'ollama', 'result' => 'ollama unreachable'],
                ['tier' => 'openrouter_free', 'result' => 'openrouter rate limited'],
            ],
            $actual->fallbackLog
        );
        $this->assertSame(1, $tier1->callCount());
        $this->assertSame(1, $tier2->callCount());
        $this->assertSame(1, $tier3->callCount());
    }

    public function test_a_genuine_request_error_propagates_immediately_without_trying_further_tiers(): void
    {
        $tier1 = new FakeLlmClient();
        // Not an LlmProviderUnavailableException — a real request-level
        // problem (e.g. what a malformed prompt or over-length context
        // would raise), per §1.4.3.
        $tier1->willThrow(new RuntimeException('the prompt was rejected as malformed'));

        $tier2 = new FakeLlmClient();
        $tier2->willReturn(new LlmTurnResult('should never be reached', DiscussionVerdict::Continue));

        $chain = new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
            ['name' => 'openrouter_free', 'client' => $tier2],
        ]);

        try {
            $chain->complete(new SystemPrompt('system'), [], 'hello');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('the prompt was rejected as malformed', $e->getMessage());
        }

        $this->assertSame(1, $tier1->callCount());
        // The whole point: a non-availability failure must not burn the
        // remaining tiers' quota on a request that would fail identically
        // everywhere.
        $this->assertSame(0, $tier2->callCount());
    }

    public function test_it_throws_no_llm_provider_available_exception_when_every_tier_is_unavailable(): void
    {
        $tier1 = new FakeLlmClient();
        $tier1->willThrow(new LlmProviderUnavailableException('ollama unreachable'));

        $tier2 = new FakeLlmClient();
        $tier2->willThrow(new LlmProviderUnavailableException('openrouter rate limited'));

        $chain = new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
            ['name' => 'openrouter_free', 'client' => $tier2],
        ]);

        try {
            $chain->complete(new SystemPrompt('system'), [], 'hello');
            $this->fail('Expected NoLlmProviderAvailableException was not thrown.');
        } catch (NoLlmProviderAvailableException $e) {
            $this->assertStringContainsString('ollama', $e->getMessage());
            $this->assertStringContainsString('openrouter_free', $e->getMessage());
        }
    }

    public function test_a_client_excluded_from_the_chain_is_never_touched(): void
    {
        // This is the mechanism behind "never silently reach a paid
        // provider unless explicitly enabled" (§1.4.4): ChainedLlmClient
        // has no concept of "paid" at all — a client that was never added
        // to its list cannot be reached by anything this class does,
        // regardless of how many configured tiers fail.
        $tier1 = new FakeLlmClient();
        $result = new LlmTurnResult('reply', DiscussionVerdict::Continue);
        $tier1->willReturn($result);

        $neverIncludedPaidClient = new FakeLlmClient();
        $neverIncludedPaidClient->willReturn(new LlmTurnResult('should never be called', DiscussionVerdict::Continue));

        $chain = new ChainedLlmClient([
            ['name' => 'ollama', 'client' => $tier1],
            // Note: $neverIncludedPaidClient is deliberately not in this array.
        ]);

        $this->assertSame($result, $chain->complete(new SystemPrompt('system'), [], 'hello'));
        $this->assertSame(0, $neverIncludedPaidClient->callCount());
    }

    public function test_an_empty_chain_throws_no_llm_provider_available_exception_immediately(): void
    {
        $chain = new ChainedLlmClient([]);

        $this->expectException(NoLlmProviderAvailableException::class);
        $this->expectExceptionMessage('no tiers configured');

        $chain->complete(new SystemPrompt('system'), [], 'hello');
    }
}
