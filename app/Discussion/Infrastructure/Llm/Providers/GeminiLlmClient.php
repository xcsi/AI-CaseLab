<?php

namespace App\Discussion\Infrastructure\Llm\Providers;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Exceptions\StructuredOutputParseException;
use App\Discussion\Infrastructure\Llm\Support\StructuredOutputParser;
use App\Discussion\LlmTurnResult;
use App\Discussion\Support\TurnClassifier;
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
    /**
     * $rawContent is the model's entire unparsed response — never safe to
     * show a student verbatim once StructuredOutputParser has rejected it,
     * whether that's a genuinely empty string (docs/15 §2.2 — a "reasoning"
     * model burning its whole max_tokens budget before ever writing content,
     * observed in real use) or a malformed/incomplete JSON attempt that
     * still contains reply_text/verdict/internal_note field names and
     * syntax. Every parse failure gets this same placeholder; verdict,
     * internal_note, and every other part of the existing
     * degrade-gracefully fallback are unchanged.
     */
    private const UNPARSEABLE_REPLY_PLACEHOLDER = "The AI's reply couldn't be read this round.";

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly bool $supportsStructuredOutput,
        private readonly StructuredOutputParser $structuredOutputParser = new StructuredOutputParser(),
        private readonly TurnClassifier $turnClassifier = new TurnClassifier(),
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

        return $this->toLlmTurnResult($response->json('candidates.0.content.parts.0.text') ?? '');
    }

    private function endpoint(): string
    {
        return "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
    }

    /**
     * Delegates interpretation entirely to StructuredOutputParser +
     * TurnClassifier, matching OpenAiCompatibleLlmClient's identical
     * scoping note — this class's only remaining job is the Gemini
     * response envelope's own text extraction (already done by the
     * caller).
     */
    private function toLlmTurnResult(string $rawContent): LlmTurnResult
    {
        try {
            $parsed = $this->structuredOutputParser->parse($rawContent);
        } catch (StructuredOutputParseException) {
            return new LlmTurnResult(
                replyText: self::UNPARSEABLE_REPLY_PLACEHOLDER,
                verdict: DiscussionVerdict::Continue,
                internalNote: 'structured parse failed, verdict defaulted',
                provider: 'gemini',
                model: $this->model,
            );
        }

        return new LlmTurnResult(
            replyText: $parsed->replyText,
            verdict: $this->turnClassifier->classify($parsed),
            evidenceReferenced: $parsed->evidenceReferenced,
            internalNote: $parsed->internalNote,
            provider: 'gemini',
            model: $this->model,
        );
    }
}
