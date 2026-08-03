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
 * The Anthropic Messages API — one implementation among several, not the
 * default (docs/13-ai-discussion-engine-design.md §1.4.1). A distinct wire
 * format from the OpenAI-compatible tiers, so it gets its own class: the
 * system prompt is a top-level field rather than a "system"-role message,
 * and only "user"/"assistant" roles are valid in the messages array.
 */
class AnthropicLlmClient implements LlmClientInterface
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

        return $this->toLlmTurnResult($response->json('content.0.text') ?? '');
    }

    /**
     * Delegates interpretation entirely to StructuredOutputParser +
     * TurnClassifier, matching OpenAiCompatibleLlmClient's identical
     * scoping note — this class's only remaining job is the Anthropic
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
                provider: 'anthropic',
                model: $this->model,
            );
        }

        return new LlmTurnResult(
            replyText: $parsed->replyText,
            verdict: $this->turnClassifier->classify($parsed),
            evidenceReferenced: $parsed->evidenceReferenced,
            internalNote: $parsed->internalNote,
            provider: 'anthropic',
            model: $this->model,
        );
    }
}
