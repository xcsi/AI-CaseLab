<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Infrastructure\Llm\Providers\OpenAiCompatibleLlmClient;
use App\Discussion\SystemPrompt;
use App\Enums\DiscussionVerdict;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves OpenAiCompatibleLlmClient's HTTP transport for Phase 14 Milestone 2,
 * per docs/14-v2-implementation-roadmap.md: success, HTTP 429, and timeout
 * responses, entirely against Http::fake() — zero real network calls. Also
 * proves the same class correctly serves two different tier configurations
 * (an Ollama-style endpoint with no API key, and an OpenRouter-style
 * endpoint with a Bearer key), which is the entire point of sharing one
 * class across three providers (docs/13-ai-discussion-engine-design.md
 * §1.4.1).
 */
class OpenAiCompatibleLlmClientTest extends TestCase
{
    public function test_it_sends_a_correctly_formed_request_and_parses_a_successful_reply(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reply_text' => 'What in the evidence points to that specifically?',
                        'verdict' => 'continue',
                        'evidence_referenced' => [1, 2],
                        'internal_note' => 'No citation yet.',
                    ])]],
                ],
            ], 200),
        ]);

        $client = new OpenAiCompatibleLlmClient(
            providerName: 'openrouter',
            baseUrl: 'https://openrouter.ai/api/v1',
            apiKey: 'or-test-key',
            model: 'meta-llama/llama-3.1-8b-instruct:free',
            maxTokens: 300,
            supportsStructuredOutput: true,
        );

        $result = $client->complete(
            new SystemPrompt('You are a senior engineer reviewing an incident investigation.'),
            [['role' => 'student', 'content' => 'I think the gateway is down.']],
            'Any more detail?'
        );

        $this->assertSame('What in the evidence points to that specifically?', $result->replyText);
        $this->assertSame(DiscussionVerdict::Continue, $result->verdict);
        $this->assertSame([1, 2], $result->evidenceReferenced);
        $this->assertSame('No citation yet.', $result->internalNote);
        $this->assertSame('openrouter', $result->provider);
        $this->assertSame('meta-llama/llama-3.1-8b-instruct:free', $result->model);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer or-test-key')
                && $request['model'] === 'meta-llama/llama-3.1-8b-instruct:free'
                && $request['max_tokens'] === 300
                && $request['messages'][0] === ['role' => 'system', 'content' => 'You are a senior engineer reviewing an incident investigation.']
                && $request['messages'][1] === ['role' => 'student', 'content' => 'I think the gateway is down.']
                && $request['messages'][2] === ['role' => 'user', 'content' => 'Any more detail?'];
        });
    }

    public function test_it_sends_no_authorization_header_for_a_local_endpoint_with_no_api_key(): void
    {
        Http::fake([
            'http://localhost:11434/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'reply_text' => 'Tell me more.',
                    'verdict' => 'continue',
                ])]]],
            ], 200),
        ]);

        $client = new OpenAiCompatibleLlmClient(
            providerName: 'ollama',
            baseUrl: 'http://localhost:11434',
            apiKey: null,
            model: 'qwen2.5:7b',
            maxTokens: 300,
            supportsStructuredOutput: false,
        );

        $client->complete(new SystemPrompt('system'), [], 'hello');

        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
    }

    public function test_a_429_response_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $client = $this->openRouterClient();

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('Rate limited by openrouter');

        $client->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_connection_timeout_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $client = $this->openRouterClient();

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('Connection to openrouter');

        $client->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_server_error_response_throws_llm_provider_unavailable_exception(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response('service unavailable', 503),
        ]);

        $client = $this->openRouterClient();

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('server error (503)');

        $client->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_a_genuine_client_error_propagates_as_a_distinct_exception_not_provider_unavailable(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'invalid api key'], 401),
        ]);

        $client = $this->openRouterClient();

        // A 401 is a genuine request-level problem (bad credentials), not a
        // "this tier is temporarily unavailable, try the next one" signal —
        // per §1.4.3 it must NOT be caught as LlmProviderUnavailableException,
        // so a retry-the-chain caller (a later milestone) doesn't waste every
        // remaining tier on a config mistake that will fail identically
        // everywhere.
        $this->expectException(RequestException::class);

        $client->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_an_unparseable_reply_falls_back_to_a_safe_default_instead_of_throwing(): void
    {
        // Proves the real StructuredOutputParser + TurnClassifier
        // integration end to end (Phase 14 Milestone 6 catch-up), not just
        // in isolation: a model that ignores the JSON contract entirely
        // must degrade this one turn, not crash the request.
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Sure! I think the gateway timeout is the issue here.']],
                ],
            ], 200),
        ]);

        $result = $this->openRouterClient()->complete(new SystemPrompt('system'), [], 'hello');

        $this->assertSame('Sure! I think the gateway timeout is the issue here.', $result->replyText);
        $this->assertSame(DiscussionVerdict::Continue, $result->verdict);
        $this->assertSame('structured parse failed, verdict defaulted', $result->internalNote);
        $this->assertSame('openrouter', $result->provider);
    }

    private function openRouterClient(): OpenAiCompatibleLlmClient
    {
        return new OpenAiCompatibleLlmClient(
            providerName: 'openrouter',
            baseUrl: 'https://openrouter.ai/api/v1',
            apiKey: 'or-test-key',
            model: 'meta-llama/llama-3.1-8b-instruct:free',
            maxTokens: 300,
            supportsStructuredOutput: true,
        );
    }
}
