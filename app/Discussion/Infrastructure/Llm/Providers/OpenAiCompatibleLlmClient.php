<?php

namespace App\Discussion\Infrastructure\Llm\Providers;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;
use App\Enums\DiscussionVerdict;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Serves openai, openrouter, AND ollama — all three speak the OpenAI Chat
 * Completions wire format (Ollama exposes it as a compatibility endpoint;
 * OpenRouter is an OpenAI-compatible router by design), so one class,
 * parameterized entirely by config, avoids three near-duplicate classes
 * drifting out of sync (docs/13-ai-discussion-engine-design.md §1.4.1's
 * provider table). LlmClientFactory (Milestone 5) instantiates this three
 * times with different config, once per tier.
 */
class OpenAiCompatibleLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $providerName,
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly bool $supportsStructuredOutput,
    ) {}

    public function complete(SystemPrompt $systemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt->text]];

        foreach ($conversationHistory as $turn) {
            $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $newMessage];

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => $this->maxTokens,
                ]);
        } catch (ConnectionException $e) {
            throw new LlmProviderUnavailableException(
                "Connection to {$this->providerName} ({$this->baseUrl}) failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->status() === 429) {
            throw new LlmProviderUnavailableException("Rate limited by {$this->providerName}.");
        }

        if ($response->serverError()) {
            throw new LlmProviderUnavailableException(
                "{$this->providerName} returned a server error ({$response->status()})."
            );
        }

        // Any other non-2xx (bad request, auth failure, etc.) is a genuine
        // request-level problem, not a "try the next tier" situation — let
        // it propagate as the real error it is (§1.4.3).
        $response->throw();

        return $this->parseHappyPath($response->json('choices.0.message.content') ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return $this->apiKey !== null && $this->apiKey !== ''
            ? ['Authorization' => "Bearer {$this->apiKey}"]
            : [];
    }

    /**
     * Minimal, native-JSON-only decode of the model's structured reply —
     * deliberately the happy path only. The full native/fallback contract
     * from §1.4.6 (including the strict-JSON-prompt fallback and
     * repair-retry for models without reliable structured output) is
     * StructuredOutputParser's job (Phase 14 Milestone 6), which will
     * replace this method's body once it exists; the request/response
     * transport this milestone builds doesn't change when that happens.
     */
    private function parseHappyPath(string $content): LlmTurnResult
    {
        $decoded = json_decode($content, true);

        return new LlmTurnResult(
            replyText: $decoded['reply_text'] ?? $content,
            verdict: DiscussionVerdict::tryFrom($decoded['verdict'] ?? '') ?? DiscussionVerdict::Continue,
            evidenceReferenced: $decoded['evidence_referenced'] ?? null,
            internalNote: $decoded['internal_note'] ?? null,
            provider: $this->providerName,
            model: $this->model,
        );
    }
}
