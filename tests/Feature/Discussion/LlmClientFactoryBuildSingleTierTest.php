<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\InvalidLlmConfigurationException;
use App\Discussion\Infrastructure\Llm\LlmClientFactory;
use App\Discussion\Infrastructure\Llm\Providers\AnthropicLlmClient;
use App\Discussion\Infrastructure\Llm\Providers\GeminiLlmClient;
use App\Discussion\Infrastructure\Llm\Providers\OpenAiCompatibleLlmClient;
use Tests\TestCase;

/**
 * Proves LlmClientFactory::buildSingleTier() for Phase 21 Milestone 1 —
 * the one addition to LlmClientFactory this milestone needed, so the
 * conformance harness (docs/13-ai-discussion-engine-design.md §15.6) can
 * build exactly one named provider's client, bypassing both config-presence
 * gating and the paid_fallback.allowed guard entirely (deliberately: this
 * validates a candidate provider *before* it's trusted in the real chain,
 * so it must be reachable regardless of that flag). build()'s own
 * behavior (tested in tests/Feature/Discussion/LlmClientFactoryTest.php,
 * Phase 14 Milestone 5) is unchanged — this only proves the new method.
 */
class LlmClientFactoryBuildSingleTierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.openrouter.api_key' => null,
            'llm.gemini.api_key' => null,
            'llm.paid_fallback.allowed' => false,
            'llm.paid_fallback.provider' => null,
            'llm.openai.api_key' => null,
            'llm.anthropic.api_key' => null,
        ]);
    }

    public function test_it_builds_ollama_regardless_of_paid_fallback_configuration(): void
    {
        $client = (new LlmClientFactory())->buildSingleTier('ollama');

        $this->assertInstanceOf(OpenAiCompatibleLlmClient::class, $client);
    }

    public function test_it_builds_openrouter_without_requiring_an_api_key_configured(): void
    {
        // isConfigured()'s api-key presence check gates build()'s chain
        // composition, not buildSingleTier() — a candidate provider being
        // validated may not have its key in config/llm.php yet at all.
        $client = (new LlmClientFactory())->buildSingleTier('openrouter');

        $this->assertInstanceOf(OpenAiCompatibleLlmClient::class, $client);
    }

    public function test_it_builds_gemini(): void
    {
        // GeminiLlmClient's $apiKey is a non-nullable string (it always
        // authenticates via a query parameter, §1.4.1) — a real key is a
        // genuine precondition for validating this provider at all, the
        // same as it would be for an actual conformance run.
        config(['llm.gemini.api_key' => 'gemini-key']);

        $client = (new LlmClientFactory())->buildSingleTier('gemini');

        $this->assertInstanceOf(GeminiLlmClient::class, $client);
    }

    public function test_it_builds_openai_even_when_paid_fallback_is_disabled(): void
    {
        // The critical guarantee: unlike build(), which structurally
        // cannot construct a paid client when paid_fallback.allowed is
        // false, buildSingleTier() must be able to, since validating a
        // paid provider is exactly how an owner would decide whether to
        // ever enable it.
        config(['llm.openai.api_key' => 'sk-openai-key']);

        $client = (new LlmClientFactory())->buildSingleTier('openai');

        $this->assertInstanceOf(OpenAiCompatibleLlmClient::class, $client);
    }

    public function test_it_builds_anthropic_even_when_paid_fallback_is_disabled(): void
    {
        config(['llm.anthropic.api_key' => 'sk-ant-key']);

        $client = (new LlmClientFactory())->buildSingleTier('anthropic');

        $this->assertInstanceOf(AnthropicLlmClient::class, $client);
    }

    public function test_an_unknown_provider_throws_immediately(): void
    {
        $this->expectException(InvalidLlmConfigurationException::class);
        $this->expectExceptionMessage('not-a-real-provider');

        (new LlmClientFactory())->buildSingleTier('not-a-real-provider');
    }
}
