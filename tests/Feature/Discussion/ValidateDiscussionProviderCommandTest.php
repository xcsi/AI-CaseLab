<?php

namespace Tests\Feature\Discussion;

use App\Models\CaseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves the `discussion:validate-provider` Artisan command's wiring for
 * Phase 21 Milestone 1 (docs/13-ai-discussion-engine-design.md §15.6) —
 * that it correctly composes LlmClientFactory::buildSingleTier(),
 * GoldenTranscripts, and GoldenTranscriptRunner end to end and reports
 * pass/fail correctly. Http::fake() intercepts every request a real
 * OpenAiCompatibleLlmClient sends (zero real network calls), scripted to
 * always answer with whatever verdict each golden transcript turn
 * expects, so this proves the harness's own wiring rather than any real
 * provider's behavior — actually validating real providers is Milestones
 * 2-4, run manually and never part of the automated suite.
 */
class ValidateDiscussionProviderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_clearly_when_the_provider_argument_is_unrecognized(): void
    {
        $exitCode = Artisan::call('discussion:validate-provider', ['provider' => 'not-a-real-provider']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown provider', $output);
    }

    public function test_it_fails_clearly_when_the_demo_case_is_not_seeded(): void
    {
        $exitCode = Artisan::call('discussion:validate-provider', ['provider' => 'ollama']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('api-returning-500-on-checkout', $output);
        $this->assertStringContainsString('DemoDataSeeder', $output);
    }

    public function test_it_reports_a_pass_for_every_transcript_when_every_reply_matches_expectations(): void
    {
        CaseModel::factory()->create([
            'slug' => 'api-returning-500-on-checkout',
            'model_solution_summary' => 'The checkout controller calls the payment gateway with no timeout configured.',
        ]);

        Http::fake(function (Request $request) {
            $data = $request->data();
            $lastMessage = end($data['messages'])['content'] ?? '';

            // Only transcript A's third turn expects "accept" — every other
            // graded turn across all four transcripts expects "continue",
            // so this one rule satisfies every transcript at once.
            $verdict = str_contains($lastMessage, 'cURL timeout after 30s') ? 'accept' : 'continue';

            return Http::response(json_encode([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reply_text' => 'A scripted, non-leaking reply.',
                        'verdict' => $verdict,
                        'evidence_gap_detected' => false,
                        'contradiction_detected' => false,
                        'internal_note' => '',
                    ])]],
                ],
            ]));
        });

        $exitCode = Artisan::call('discussion:validate-provider', ['provider' => 'ollama']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Evidence-requirement', $output);
        $this->assertStringContainsString('Sycophancy', $output);
        $this->assertStringContainsString('Injection', $output);
        $this->assertStringContainsString('Contradiction', $output);
        $this->assertStringNotContainsString('FAIL', $output);
    }
}
