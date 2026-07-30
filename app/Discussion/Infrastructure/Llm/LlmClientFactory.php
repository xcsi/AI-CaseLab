<?php

namespace App\Discussion\Infrastructure\Llm;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\InvalidLlmConfigurationException;
use App\Discussion\Infrastructure\Llm\Providers\AnthropicLlmClient;
use App\Discussion\Infrastructure\Llm\Providers\GeminiLlmClient;
use App\Discussion\Infrastructure\Llm\Providers\OpenAiCompatibleLlmClient;

/**
 * Assembles the cost-safe ordered fallback chain from config/llm.php, per
 * docs/13-ai-discussion-engine-design.md §1.4. This is the one place in the
 * codebase where "which provider" is ever a live decision — DiscussionService
 * (a later phase) is handed the finished LlmClientInterface and never makes
 * this decision itself.
 *
 * The fixed tier order (§1.4.1) is hardcoded here, deliberately not read
 * from config: Ollama, then OpenRouter (if configured), then Gemini (if
 * configured), then — only if the owner has explicitly enabled it — exactly
 * one paid provider. That last step is the literal mechanism behind "never
 * silently reach a paid provider": buildPaidClient() is called from exactly
 * one place in this file, and that call site is lexically inside the
 * paid-fallback-allowed guard below — there is no path through this class
 * that constructs an OpenAI or Anthropic client when that flag is false,
 * not a runtime check that happens to skip it.
 */
class LlmClientFactory
{
    public function build(): LlmClientInterface
    {
        $tiers = [
            ['name' => 'ollama', 'client' => $this->buildOllamaClient()],
        ];

        if ($this->isConfigured('openrouter')) {
            $tiers[] = ['name' => 'openrouter', 'client' => $this->buildOpenAiCompatibleClient('openrouter')];
        }

        if ($this->isConfigured('gemini')) {
            $tiers[] = ['name' => 'gemini', 'client' => $this->buildGeminiClient()];
        }

        if (config('llm.paid_fallback.allowed') === true) {
            $tiers[] = ['name' => config('llm.paid_fallback.provider'), 'client' => $this->buildPaidClient()];
        }

        return new ChainedLlmClient($tiers);
    }

    /**
     * Ollama is always a candidate (§1.4.1) — an unreachable local instance
     * fails its own request with LlmProviderUnavailableException at call
     * time (Milestone 2), which ChainedLlmClient already handles; there's
     * nothing to gate at factory time.
     */
    private function buildOllamaClient(): LlmClientInterface
    {
        return new OpenAiCompatibleLlmClient(
            providerName: 'ollama',
            baseUrl: config('llm.ollama.base_url'),
            apiKey: null,
            model: config('llm.ollama.model'),
            maxTokens: config('llm.max_tokens'),
            supportsStructuredOutput: config('llm.ollama.supports_structured_output'),
        );
    }

    private function buildOpenAiCompatibleClient(string $tier): LlmClientInterface
    {
        return new OpenAiCompatibleLlmClient(
            providerName: $tier,
            baseUrl: config("llm.{$tier}.base_url"),
            apiKey: config("llm.{$tier}.api_key"),
            model: config("llm.{$tier}.model"),
            maxTokens: config('llm.max_tokens'),
            supportsStructuredOutput: config("llm.{$tier}.supports_structured_output"),
        );
    }

    private function buildGeminiClient(): LlmClientInterface
    {
        return new GeminiLlmClient(
            apiKey: config('llm.gemini.api_key'),
            model: config('llm.gemini.model'),
            maxTokens: config('llm.max_tokens'),
            supportsStructuredOutput: config('llm.gemini.supports_structured_output'),
        );
    }

    /**
     * Only ever called from build(), and only from inside the
     * paid_fallback.allowed guard — see this class's docblock.
     */
    private function buildPaidClient(): LlmClientInterface
    {
        $provider = config('llm.paid_fallback.provider');

        return match ($provider) {
            'openai' => new OpenAiCompatibleLlmClient(
                providerName: 'openai',
                baseUrl: config('llm.openai.base_url'),
                apiKey: config('llm.openai.api_key'),
                model: config('llm.openai.model'),
                maxTokens: config('llm.max_tokens'),
                supportsStructuredOutput: config('llm.openai.supports_structured_output'),
            ),
            'anthropic' => new AnthropicLlmClient(
                apiKey: config('llm.anthropic.api_key'),
                model: config('llm.anthropic.model'),
                maxTokens: config('llm.max_tokens'),
                supportsStructuredOutput: config('llm.anthropic.supports_structured_output'),
            ),
            default => throw new InvalidLlmConfigurationException(
                "LLM_ALLOW_PAID_FALLBACK is true but LLM_PAID_FALLBACK_PROVIDER "
                .(is_string($provider) && $provider !== '' ? "(\"{$provider}\")" : '(unset)')
                .' is not "openai" or "anthropic". Paid fallback was explicitly enabled — fix the'
                .' provider name rather than leaving it silently unresolved.'
            ),
        };
    }

    private function isConfigured(string $tier): bool
    {
        $apiKey = config("llm.{$tier}.api_key");

        return $apiKey !== null && $apiKey !== '';
    }
}
