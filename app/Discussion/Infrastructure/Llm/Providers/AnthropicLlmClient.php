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
 * The Anthropic Messages API — one implementation among several, not the
 * default (docs/13-ai-discussion-engine-design.md §1.4.1). A distinct wire
 * format from the OpenAI-compatible tiers, so it gets its own class: the
 * system prompt is a top-level field rather than a "system"-role message,
 * and only "user"/"assistant" roles are valid in the messages array.
 */
class AnthropicLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly bool $supportsStructuredOutput,
    ) {}

    public function complete(SystemPrompt $systemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult
    {
        $messages = [];

        foreach ($conversationHistory as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'ai' ? 'assistant' : 'user',
                'content' => $turn['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $newMessage];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system' => $systemPrompt->text,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            throw new LlmProviderUnavailableException(
                "Connection to anthropic failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->status() === 429) {
            throw new LlmProviderUnavailableException('Rate limited by anthropic.');
        }

        if ($response->serverError()) {
            throw new LlmProviderUnavailableException(
                "anthropic returned a server error ({$response->status()})."
            );
        }

        // A genuine request-level problem (bad key, malformed request) is
        // not "try the next tier" — propagates as-is, per §1.4.3.
        $response->throw();

        return $this->parseHappyPath($response->json('content.0.text') ?? '');
    }

    /**
     * Minimal, native-JSON-only decode of the model's structured reply —
     * deliberately the happy path only, matching
     * OpenAiCompatibleLlmClient's identical scoping note. Replaced by
     * StructuredOutputParser (Phase 14 Milestone 6).
     */
    private function parseHappyPath(string $content): LlmTurnResult
    {
        $decoded = json_decode($content, true);

        return new LlmTurnResult(
            replyText: $decoded['reply_text'] ?? $content,
            verdict: DiscussionVerdict::tryFrom($decoded['verdict'] ?? '') ?? DiscussionVerdict::Continue,
            evidenceReferenced: $decoded['evidence_referenced'] ?? null,
            internalNote: $decoded['internal_note'] ?? null,
            provider: 'anthropic',
            model: $this->model,
        );
    }
}
