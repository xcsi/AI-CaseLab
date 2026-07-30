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
 * The Google Generative Language API — a distinct wire format from both the
 * OpenAI-compatible tiers and Anthropic (docs/13-ai-discussion-engine-design.md
 * §1.4.1): the system prompt is a top-level "system_instruction", the
 * conversation array is "contents" with "user"/"model" roles (not
 * "assistant"), and authentication is a "key" query parameter rather than a
 * header.
 */
class GeminiLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly bool $supportsStructuredOutput,
    ) {}

    public function complete(SystemPrompt $systemPrompt, array $conversationHistory, string $newMessage): LlmTurnResult
    {
        $contents = [];

        foreach ($conversationHistory as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'ai' ? 'model' : 'user',
                'parts' => [['text' => $turn['content']]],
            ];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $newMessage]]];

        try {
            $response = Http::timeout(30)
                ->post($this->endpoint(), [
                    'system_instruction' => ['parts' => [['text' => $systemPrompt->text]]],
                    'contents' => $contents,
                    'generationConfig' => ['maxOutputTokens' => $this->maxTokens],
                ]);
        } catch (ConnectionException $e) {
            throw new LlmProviderUnavailableException(
                "Connection to gemini failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->status() === 429) {
            throw new LlmProviderUnavailableException('Rate limited by gemini.');
        }

        if ($response->serverError()) {
            throw new LlmProviderUnavailableException(
                "gemini returned a server error ({$response->status()})."
            );
        }

        // A genuine request-level problem (bad key, malformed request) is
        // not "try the next tier" — propagates as-is, per §1.4.3.
        $response->throw();

        return $this->parseHappyPath($response->json('candidates.0.content.parts.0.text') ?? '');
    }

    private function endpoint(): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
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
            provider: 'gemini',
            model: $this->model,
        );
    }
}
