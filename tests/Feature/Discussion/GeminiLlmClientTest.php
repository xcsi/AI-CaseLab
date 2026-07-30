<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Infrastructure\Llm\Providers\GeminiLlmClient;
use App\Discussion\SystemPrompt;
use App\Enums\DiscussionVerdict;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves GeminiLlmClient's HTTP transport for Phase 14 Milestone 3, per
 * docs/14-v2-implementation-roadmap.md — same Http::fake() discipline as the
 * other two provider clients, adapted to the Generative Language API's
 * distinct wire format: "system_instruction", "contents" with
 * "user"/"model" roles (not "assistant"), and a "key" query parameter
 * instead of an Authorization header.
 */
class GeminiLlmClientTest extends TestCase
{
    public function test_it_sends_a_correctly_formed_request_and_parses_a_successful_reply(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['role' => 'model', 'parts' => [['text' => json_encode([
                        'reply_text' => 'What would you expect to see if that were true?',
                        'verdict' => 'continue',
                        'evidence_referenced' => [2],
                    ])]]]],
                ],
            ], 200),
        ]);

        $client = new GeminiLlmClient(
            apiKey: 'gemini-test-key',
            model: 'gemini-1.5-flash',
            maxTokens: 300,
            supportsStructuredOutput: true,
        );

        $result = $client->complete(
            new SystemPrompt('You are a mentor reviewing an investigation.'),
            [['role' => 'student', 'content' => 'My theory is X.'], ['role' => 'ai', 'content' => 'Why do you think that?']],
            'Because of the log.'
        );

        $this->assertSame('What would you expect to see if that were true?', $result->replyText);
        $this->assertSame(DiscussionVerdict::Continue, $result->verdict);
        $this->assertSame([2], $result->evidenceReferenced);
        $this->assertSame('gemini', $result->provider);
        $this->assertSame('gemini-1.5-flash', $result->model);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent')
                && str_contains($request->url(), 'key=gemini-test-key')
                && $request['system_instruction']['parts'][0]['text'] === 'You are a mentor reviewing an investigation.'
                && $request['contents'][0] === ['role' => 'user', 'parts' => [['text' => 'My theory is X.']]]
                // AI turns map to Gemini's "model" role, not "assistant"
                && $request['contents'][1] === ['role' => 'model', 'parts' => [['text' => 'Why do you think that?']]]
                && $request['contents'][2] === ['role' => 'user', 'parts' => [['text' => 'Because of the log.']]]
                && $request['generationConfig']['maxOutputTokens'] === 300;
        });
    }

    public function test_a_429_response_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'quota exceeded'], 429)]);

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('Rate limited by gemini');

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_connection_timeout_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('Connection to gemini');

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_server_error_response_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('internal error', 500)]);

        $this->expectException(LlmProviderUnavailableException::class);

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_genuine_client_error_propagates_as_a_distinct_exception(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'API key not valid'], 400)]);

        $this->expectException(RequestException::class);

        $this->client()->complete(new SystemPrompt('system'), [], 'hello');
    }

    private function client(): GeminiLlmClient
    {
        return new GeminiLlmClient(
            apiKey: 'gemini-test-key',
            model: 'gemini-1.5-flash',
            maxTokens: 300,
            supportsStructuredOutput: true,
        );
    }
}
