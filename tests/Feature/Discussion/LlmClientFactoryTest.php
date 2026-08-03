<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\InvalidLlmConfigurationException;
use App\Discussion\Infrastructure\Llm\LlmClientFactory;
use Tests\TestCase;

/**
 * Proves LlmClientFactory builds the correct chain composition for every
 * configuration shape named in Phase 14 Milestone 5's requirements, per
 * docs/13-ai-discussion-engine-design.md §1.4. No Http::fake() needed here —
 * this class only decides *which clients to construct and in what order*;
 * it never calls complete() itself, so no network boundary is crossed by
 * building a chain, only by using one (already proven client-by-client in
 * Milestones 2-3 and mechanism-wise in Milestone 4).
 *
 * The single most important test in this file — arguably in Version 2 so
 * far — is test_the_paid_tier_is_absent_when_disabled_even_with_valid_looking_keys_present():
 * it proves the "never silently reach a paid provider" guarantee holds even
 * when an owner has (mistakenly or not) left real-looking API keys sitting
 * in config, because config/llm.php's default is exactly that shape — keys
 * present, flag off.
 */
class LlmClientFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Start every test from a clean slate — nothing configured beyond
        // config/llm.php's own shipped defaults (Ollama only, paid disabled).
        config([
            'llm.openrouter.api_key' => null,
            'llm.gemini.api_key' => null,
            'llm.paid_fallback.allowed' => false,
            'llm.paid_fallback.provider' => null,
            'llm.openai.api_key' => null,
            'llm.anthropic.api_key' => null,
        ]);
    }

    public function test_local_only_when_nothing_else_is_configured(): void
    {
        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama'], $chain->tierNames());
    }

    public function test_local_plus_openrouter_when_only_openrouter_is_configured(): void
    {
        config(['llm.openrouter.api_key' => 'or-key']);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'openrouter'], $chain->tierNames());
    }

    public function test_local_plus_openrouter_plus_gemini_when_both_are_configured(): void
    {
        config([
            'llm.openrouter.api_key' => 'or-key',
            'llm.gemini.api_key' => 'gemini-key',
        ]);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'openrouter', 'gemini'], $chain->tierNames());
    }

    public function test_gemini_alone_without_openrouter_still_slots_in_after_ollama(): void
    {
        config(['llm.gemini.api_key' => 'gemini-key']);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'gemini'], $chain->tierNames());
    }

    public function test_the_paid_tier_is_absent_when_disabled_even_with_valid_looking_keys_present(): void
    {
        // The critical scenario: an owner has real-looking paid API keys
        // sitting in config (left over from testing, or configured "just in
        // case") but never flipped LLM_ALLOW_PAID_FALLBACK. This must
        // produce a chain with zero paid tiers, full stop.
        config([
            'llm.openrouter.api_key' => 'or-key',
            'llm.gemini.api_key' => 'gemini-key',
            'llm.paid_fallback.allowed' => false,
            'llm.paid_fallback.provider' => 'openai',
            'llm.openai.api_key' => 'sk-live-looks-completely-real',
            'llm.anthropic.api_key' => 'sk-ant-also-looks-completely-real',
        ]);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'openrouter', 'gemini'], $chain->tierNames());
        $this->assertNotContains('openai', $chain->tierNames());
        $this->assertNotContains('anthropic', $chain->tierNames());
    }

    public function test_the_paid_tier_is_appended_last_when_explicitly_enabled_with_openai(): void
    {
        config([
            'llm.openrouter.api_key' => 'or-key',
            'llm.paid_fallback.allowed' => true,
            'llm.paid_fallback.provider' => 'openai',
            'llm.openai.api_key' => 'sk-live-key',
        ]);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'openrouter', 'openai'], $chain->tierNames());
    }

    public function test_the_paid_tier_is_appended_last_when_explicitly_enabled_with_anthropic(): void
    {
        config([
            'llm.paid_fallback.allowed' => true,
            'llm.paid_fallback.provider' => 'anthropic',
            'llm.anthropic.api_key' => 'sk-ant-live-key',
        ]);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'anthropic'], $chain->tierNames());
    }

    public function test_enabling_paid_fallback_still_tries_free_tiers_first(): void
    {
        // Enabling paid fallback means "allow tier 4 to exist," not "prefer
        // tier 4" (§1.4.4) — it must still be last in the order, never
        // promoted ahead of the free tiers.
        config([
            'llm.openrouter.api_key' => 'or-key',
            'llm.gemini.api_key' => 'gemini-key',
            'llm.paid_fallback.allowed' => true,
            'llm.paid_fallback.provider' => 'anthropic',
            'llm.anthropic.api_key' => 'sk-ant-live-key',
        ]);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama', 'openrouter', 'gemini', 'anthropic'], $chain->tierNames());
    }

    public function test_enabling_paid_fallback_with_an_unrecognized_provider_throws_immediately(): void
    {
        config([
            'llm.paid_fallback.allowed' => true,
            'llm.paid_fallback.provider' => 'not-a-real-provider',
        ]);

        $this->expectException(InvalidLlmConfigurationException::class);
        $this->expectExceptionMessage('not-a-real-provider');

        (new LlmClientFactory())->build();
    }

    public function test_enabling_paid_fallback_with_no_provider_named_throws_immediately(): void
    {
        config([
            'llm.paid_fallback.allowed' => true,
            'llm.paid_fallback.provider' => null,
        ]);

        $this->expectException(InvalidLlmConfigurationException::class);
        $this->expectExceptionMessage('unset');

        (new LlmClientFactory())->build();
    }

    public function test_an_empty_string_api_key_is_treated_the_same_as_unconfigured(): void
    {
        config(['llm.openrouter.api_key' => '']);

        $chain = (new LlmClientFactory())->build();

        $this->assertSame(['ollama'], $chain->tierNames());
    }
}
