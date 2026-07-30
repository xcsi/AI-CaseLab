<?php

namespace App\Console\Commands;

use App\Discussion\Conformance\GoldenTranscript;
use App\Discussion\Conformance\GoldenTranscriptRunner;
use App\Discussion\Conformance\GoldenTranscripts;
use App\Discussion\Conformance\TranscriptRunResult;
use App\Discussion\Contracts\AiPersonaInterface;
use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Infrastructure\Llm\LlmClientFactory;
use App\Discussion\PersonaResolver;
use App\Discussion\Subjects\CaseAttemptDiscussionSubject;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use Illuminate\Console\Command;
use Throwable;

/**
 * The §15.6 provider behavioral conformance harness (docs/13-ai-discussion-engine-design.md
 * §15), implemented for Phase 21 Milestone 1 — a separate, manually-invoked
 * tool, deliberately NOT part of the standard automated test suite: it
 * calls a real, configured provider/model and costs whatever that
 * provider costs (including "nothing," for the free tiers this design
 * defaults to).
 *
 * Runs every golden transcript (§15.4) against the named provider tier 3
 * times each and reports pass/fail per §15.6's criteria. This milestone
 * builds the harness only — actually running it against real providers
 * and recording results is Milestones 2-4.
 */
class ValidateDiscussionProviderCommand extends Command
{
    protected $signature = 'discussion:validate-provider {provider : ollama, openrouter, gemini, openai, or anthropic}';

    protected $description = 'Runs the golden-transcript behavioral conformance suite (docs/13 §15) against one configured LLM provider tier.';

    private const RUNS_PER_TRANSCRIPT = 3;

    private const REQUIRED_PASSES = 2;

    public function handle(): int
    {
        $provider = (string) $this->argument('provider');

        try {
            $client = (new LlmClientFactory())->buildSingleTier($provider);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $case = CaseModel::where('slug', 'api-returning-500-on-checkout')->first();

        if (! $case) {
            // Two short lines, not one long one — Symfony's error() block
            // word-wraps at the terminal width, which can otherwise split
            // a long single message mid-word.
            $this->error('The demo case ("api-returning-500-on-checkout") isn\'t seeded.');
            $this->line('The golden transcripts (§15.4) are grounded in it — run this first: php artisan db:seed --class=DemoDataSeeder');

            return Command::FAILURE;
        }

        // Deliberately never persisted — this harness doesn't need a real
        // attempt row, and shouldn't leave one behind every time it runs.
        $attempt = new CaseAttempt(['case_id' => $case->id]);
        $attempt->setRelation('case', $case);

        $subject = new CaseAttemptDiscussionSubject($attempt);
        $personaResolver = app(PersonaResolver::class);
        $runner = new GoldenTranscriptRunner();

        $this->info("Validating provider \"{$provider}\" against §15.4's golden transcripts (".self::RUNS_PER_TRANSCRIPT.' runs each)...');
        $this->newLine();

        $rows = [];
        $overallPassed = true;

        foreach (GoldenTranscripts::all() as $transcript) {
            $persona = $personaResolver->resolve($transcript->persona);
            $runResults = [];

            for ($i = 0; $i < self::RUNS_PER_TRANSCRIPT; $i++) {
                $runResults[] = $this->runOnce($runner, $client, $persona, $subject, $transcript, $case->model_solution_summary ?? '');
            }

            [$row, $transcriptPassed] = $this->summarize($transcript, $runResults);
            $rows[] = $row;
            $overallPassed = $overallPassed && $transcriptPassed;
        }

        $this->table(['Transcript', 'Persona', 'Runs Passed', 'Forbidden Violation', 'Result'], $rows);

        $this->newLine();
        $this->line('Verdict matching is the automated signal only — per §15.6, a borderline '
            .'result should still get a human read of the actual transcript text above, not '
            .'just this table.');

        return $overallPassed ? Command::SUCCESS : Command::FAILURE;
    }

    private function runOnce(
        GoldenTranscriptRunner $runner,
        LlmClientInterface $client,
        AiPersonaInterface $persona,
        CaseAttemptDiscussionSubject $subject,
        GoldenTranscript $transcript,
        string $modelSolutionSummary,
    ): ?TranscriptRunResult {
        try {
            $result = $runner->run($client, $persona, $subject, $transcript, $modelSolutionSummary);
        } catch (Throwable $e) {
            $this->warn("  [{$transcript->name}] run errored: {$e->getMessage()}");

            return null;
        }

        foreach ($result->turnResults as $turnResult) {
            $marker = ! $turnResult->graded ? '·' : ($turnResult->verdictMatched() ? '✓' : '✗');
            $leakMarker = $turnResult->leaked ? ' [LEAK DETECTED]' : '';
            $this->line("  [{$transcript->name}] {$marker} \"{$turnResult->studentMessage}\"".$leakMarker);
            $this->line('      -> '.$turnResult->replyText);
        }

        return $result;
    }

    /**
     * @param  array<int, ?TranscriptRunResult>  $runResults
     * @return array{0: array<int, string>, 1: bool}
     */
    private function summarize(GoldenTranscript $transcript, array $runResults): array
    {
        $completedRuns = array_filter($runResults, fn (?TranscriptRunResult $r) => $r !== null);
        $passCount = count(array_filter($completedRuns, fn (TranscriptRunResult $r) => $r->passed()));
        $forbiddenViolation = count(array_filter($completedRuns, fn (TranscriptRunResult $r) => $r->hasForbiddenViolation())) > 0;
        $erroredRuns = count($runResults) - count($completedRuns);

        // §15.3: any forbidden-behavior violation is an automatic fail,
        // never averaged against an otherwise-good pass rate.
        $passed = ! $forbiddenViolation && $passCount >= self::REQUIRED_PASSES;

        $row = [
            $transcript->name,
            $transcript->persona,
            $passCount.' / '.self::RUNS_PER_TRANSCRIPT.($erroredRuns > 0 ? " ({$erroredRuns} errored)" : ''),
            $forbiddenViolation ? 'YES' : 'no',
            $passed ? 'PASS' : 'FAIL',
        ];

        return [$row, $passed];
    }
}
