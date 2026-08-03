<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\LlmTurnResult;
use App\Discussion\SystemPrompt;
use App\Discussion\Exceptions\LlmProviderUnavailableException;
use App\Discussion\Testing\FakeLlmClient;
use App\Enums\DiscussionVerdict;
use RuntimeException;
use Tests\TestCase;

/**
 * Proves the one testing tool every later Discussion Engine phase depends on
 * actually works, per docs/14-v2-implementation-roadmap.md's Phase 14
 * Milestone 1: FakeLlmClient is bound to LlmClientInterface in the testing
 * environment, scripted responses return in order, and every call is
 * recorded for assertions — all without a single real network call.
 */
class FakeLlmClientTest extends TestCase
{
    public function test_the_container_resolves_llm_client_interface_to_the_fake_in_testing_environment(): void
    {
        $this->assertInstanceOf(FakeLlmClient::class, app(LlmClientInterface::class));
    }

    public function test_it_returns_queued_responses_in_order(): void
    {
        $fake = new FakeLlmClient();
        $first = new LlmTurnResult('first reply', DiscussionVerdict::Continue);
        $second = new LlmTurnResult('second reply', DiscussionVerdict::Accept);

        $fake->willReturn($first)->willReturn($second);

        $this->assertSame($first, $fake->complete(new SystemPrompt('system'), [], 'hello'));
        $this->assertSame($second, $fake->complete(new SystemPrompt('system'), [], 'goodbye'));
    }

    public function test_it_records_every_call_with_its_arguments(): void
    {
        $fake = new FakeLlmClient();
        $fake->willReturn(new LlmTurnResult('reply', DiscussionVerdict::Continue));

        $systemPrompt = new SystemPrompt('you are a senior engineer');
        $history = [['role' => 'student', 'content' => 'my theory is...']];

        $fake->complete($systemPrompt, $history, 'a new message');

        $this->assertSame(1, $fake->callCount());
        $recorded = $fake->recordedCalls()[0];
        $this->assertSame($systemPrompt, $recorded['systemPrompt']);
        $this->assertSame($history, $recorded['conversationHistory']);
        $this->assertSame('a new message', $recorded['newMessage']);
    }

    public function test_it_throws_a_clear_error_when_the_queue_is_exhausted(): void
    {
        $fake = new FakeLlmClient();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no scripted response queued');

        $fake->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_it_throws_a_queued_exception_instead_of_returning(): void
    {
        $fake = new FakeLlmClient();
        $fake->willThrow(new LlmProviderUnavailableException('tier unavailable'));

        $this->expectException(LlmProviderUnavailableException::class);
        $this->expectExceptionMessage('tier unavailable');

        $fake->complete(new SystemPrompt('system'), [], 'hello');
    }

    public function test_willreturn_and_willthrow_can_be_mixed_in_the_same_queue(): void
    {
        $fake = new FakeLlmClient();
        $success = new LlmTurnResult('reply', DiscussionVerdict::Continue);
        $fake->willThrow(new LlmProviderUnavailableException('first attempt fails'))->willReturn($success);

        try {
            $fake->complete(new SystemPrompt('system'), [], 'first message');
            $this->fail('Expected LlmProviderUnavailableException was not thrown.');
        } catch (LlmProviderUnavailableException) {
            // expected
        }

        $this->assertSame($success, $fake->complete(new SystemPrompt('system'), [], 'second message'));
        $this->assertSame(2, $fake->callCount());
    }
}
