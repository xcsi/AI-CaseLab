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
    /**
     * $rawContent is the model's entire unparsed response — never safe to
     * show a student verbatim once StructuredOutputParser has rejected it,
     * whether that's a genuinely empty string (a "reasoning" model burning
     * its whole max_tokens budget on a reasoning field before ever writing
     * content, observed in real use with an OpenRouter free-tier model —
     * docs/15 §2.2) or a malformed/incomplete JSON attempt that still
     * contains reply_text/verdict/internal_note field names and syntax.
     * Every parse failure gets this same placeholder; verdict, internal_note,
     * and every other part of the existing degrade-gracefully fallback are
     * unchanged.
     */
    private const UNPARSEABLE_REPLY_PLACEHOLDER = "The AI's reply couldn't be read this round.";

    public function __construct(
        private readonly string $providerName,
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly bool $supportsStructuredOutput,
        private readonly StructuredOutputParser $structuredOutputParser = new StructuredOutputParser(),
        private readonly TurnClassifier $turnClassifier = new TurnClassifier(),
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

        return $this->toLlmTurnResult($response->json('choices.0.message.content') ?? '');
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
     * Delegates interpretation entirely to StructuredOutputParser +
     * TurnClassifier — this class's only remaining job is extracting the
     * raw text content from its own response envelope (already done by the
     * caller) and, on a parse failure, applying the documented safe
     * fallback (§1.4.6): degrade this one turn, never crash the state
     * machine. This fallback-construction step is intentionally duplicated
     * across all three provider clients rather than pushed into
     * StructuredOutputParser — a defaulted verdict is exactly the kind of
     * inference that class must never do.
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
                provider: $this->providerName,
                model: $this->model,
            );
        }

        return new LlmTurnResult(
            replyText: $parsed->replyText,
            verdict: $this->turnClassifier->classify($parsed),
            evidenceReferenced: $parsed->evidenceReferenced,
            internalNote: $parsed->internalNote,
            provider: $this->providerName,
            model: $this->model,
        );
    }
}
