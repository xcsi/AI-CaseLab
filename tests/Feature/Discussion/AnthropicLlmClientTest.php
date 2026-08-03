<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Infrastructure\Llm\Providers\AnthropicLlmClient;
use App\Discussion\SystemPrompt;
use App\Enums\DiscussionVerdict;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves AnthropicLlmClient's HTTP transport for Phase 14 Milestone 3, per
 * docs/14-v2-implementation-roadmap.md — same Http::fake() discipline as
 * OpenAiCompatibleLlmClientTest, adapted to the Messages API's distinct wire
 * format: a top-level "system" field (not a system-role message) and
 * "user"/"assistant" roles only.
 */
class AnthropicLlmClientTest extends TestCase
{
    public function test_it_sends_a_correctly_formed_request_and_parses_a_successful_reply(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode([
                        'reply_text' => 'Where in the log, specifically?',
                        'verdict' => 'continue',
                        'evidence_referenced' => [3],
                        'internal_note' => 'Vague citation.',
                    ])],
                ],
            ], 200),
        ]);

        $client = new AnthropicLlmClient(
            apiKey: 'anthropic-test-key',
            model: 'claude-3-5-haiku-20241022',
            maxTokens: 300,
            supportsStructuredOutput: true,
        );

        $result = $client->complete(
            new SystemPrompt('You are a strict technical interviewer.'),
            [['role' => 'student', 'content' => 'The log shows an error.'], ['role' => 'ai', 'content' => 'Which one?']],
            'The timeout one.'
        );

        $this->assertSame('Where in the log, specifically?', $result->replyText);
        $this->assertSame(DiscussionVerdict::Continue, $result->verdict);
        $this->assertSame([3], $result->evidenceReferenced);
        $this->assertSame('anthropic', $result->provider);
        $this->assertSame('claude-3-5-haiku-20241022', $result->model);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'anthropic-test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                // System prompt is a top-level field, never a message in the array
                && $request['system'] === 'You are a strict technical interviewer.'
                && $request['messages'][0] === ['role' => 'user', 'content' => 'The log shows an error.']
                && $request['messages'][1] === ['role' => 'assistant', 'content' => 'Which one?']
                && $request['messages'][2] === ['role' => 'user', 'content' => 'The timeout one.']
                // Messages array must never contain a "system" role for this provider
                && ! collect($request['messages'])->pluck('role')->contains('system');
        });
    }

    public function test_a_429_response_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response(['error' => 'rate limited'], 429)]);

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('Rate limited by anthropic');

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_connection_timeout_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('Connection to anthropic');

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_server_error_response_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response('overloaded', 529)]);

        $this->expectException(LlmProviderUnavailableException::class);

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_genuine_client_error_propagates_as_a_distinct_exception(): void
    {
        Http::fake(['https://api.anthropic.com/v1/messages' => Http::response(['error' => 'invalid x-api-key'], 401)]);

        $this->expectException(RequestException::class);

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_truly_empty_reply_falls_back_to_a_placeholder_message_instead_of_a_blank_bubble(): void
    {
        // A real observed case (docs/15 §2.2): a "reasoning" model can burn
        // its whole max_tokens budget and return an empty content string —
        // not malformed JSON, genuinely nothing. The blank-string fallback
        // used to reach the student as an empty reply bubble.
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => ''],
                ],
            ], 200),
        ]);

        $result = $this->client()->complete(new SystemPrompt('system'), [], 'hello');

        $this->assertSame("The AI's reply couldn't be read this round.", $result->replyText);
        $this->assertSame(DiscussionVerdict::Continue, $result->verdict);
        $this->assertSame('structured parse failed, verdict defaulted', $result->internalNote);
        $this->assertSame('anthropic', $result->provider);
    }

    public function test_a_malformed_json_reply_never_leaks_raw_json_to_the_student(): void
    {
        // Release blocker regression: a model that attempted the JSON
        // contract but produced something json_decode can't parse used to
        // fall through to the raw content verbatim — exposing
        // reply_text/verdict/internal_note field names and JSON syntax
        // directly in the chat bubble. Every parse failure must resolve to
        // the same safe placeholder, never the raw content.
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"reply_text": "What in the evidence points to that specif'],
                ],
            ], 200),
        ]);

        $result = $this->client()->complete(new SystemPrompt('system'), [], 'hello');

        $this->assertSame("The AI's reply couldn't be read this round.", $result->replyText);
        $this->assertStringNotContainsString('reply_text', $result->replyText);
        $this->assertStringNotContainsString('{', $result->replyText);
        $this->assertSame(DiscussionVerdict::Continue, $result->verdict);
    }

    private function client(): AnthropicLlmClient
    {
        return new AnthropicLlmClient(
            apiKey: 'anthropic-test-key',
            model: 'claude-3-5-haiku-20241022',
            maxTokens: 300,
            supportsStructuredOutput: true,
        );
    }
}
