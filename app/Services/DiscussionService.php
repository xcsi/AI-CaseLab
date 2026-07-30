<?php

namespace App\Services;

use App\Discussion\Contracts\AiPersonaInterface;
use App\Discussion\Contracts\LlmClientInterface;
use App\Discussion\Exceptions\DiscussionAlreadyActiveException;
use App\Discussion\Exceptions\DiscussionNotActiveException;
use App\Discussion\Exceptions\LeakedReplyException;
use App\Discussion\PersonaResolver;
use App\Discussion\Subjects\CaseAttemptDiscussionSubject;
use App\Discussion\Support\LeakageGuard;
use App\Discussion\Support\SystemPromptBuilder;
use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Enums\DiscussionVerdict;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Models\DiscussionTurn;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orchestrates a discussion turn end to end: persist the student's message
 * -> build context -> call the LLM -> guard the reply -> persist the AI's
 * turn -> update session state (docs/13-ai-discussion-engine-design.md §1.2,
 * §2). Depends only on LlmClientInterface — never knows which provider or
 * how many fallback tiers answered a given call (§1.4) — and only on
 * AiPersonaInterface/DiscussionSubjectInterface for the "who/how" and
 * "what" of a session, never on persona- or subject-specific behavior
 * beyond those two interfaces (§1.5).
 *
 * The one deliberate exception to subject-abstraction purity: the
 * LeakageGuard check needs specifically the sensitive *answer* text, not
 * the full ground truth blob DiscussionSubjectInterface::groundTruthContext()
 * returns (which also legitimately includes evidence content the AI is
 * *supposed* to quote when discussing it with the student — flagging those
 * quotes as leaks would be a false positive). DiscussionSubjectInterface
 * has no dedicated "sensitive answer only" method, and adding one would be
 * a frozen-interface change outside this milestone's scope, so this class
 * reads `$attempt->case->model_solution_summary` directly for that one
 * check. Every other use of the subject goes through
 * CaseAttemptDiscussionSubject as designed.
 *
 * DiscussionAccepted event firing is deliberately NOT built here — that is
 * Phase 16 Milestone 2, per the roadmap. This milestone only sets session
 * status; nothing subscribes to it yet.
 */
class DiscussionService
{
    public function __construct(
        private readonly PersonaResolver $personaResolver,
        private readonly SystemPromptBuilder $systemPromptBuilder,
        private readonly LlmClientInterface $llmClient,
        private readonly LeakageGuard $leakageGuard,
    ) {}

    /**
     * §2.1: "Not Started" -> "Active (round 1)". The opening student
     * position is turn 1; the AI's first challenge (turn 2) is generated
     * immediately, matching the state diagram's "student submits opening
     * position -> AI turn: challenge/probe" sequence.
     */
    public function start(CaseAttempt $attempt, string $openingPosition, ?string $persona = null): DiscussionSession
    {
        $persona ??= $attempt->case->discussion_default_persona;

        if ($persona === null) {
            throw new InvalidArgumentException(
                'No persona specified and this case has no default persona configured.'
            );
        }

        $this->ensureNoActiveSessionExists($attempt);

        return DB::transaction(function () use ($attempt, $openingPosition, $persona) {
            $personaInstance = $this->personaResolver->resolve($persona);

            $session = DiscussionSession::create([
                'discussable_type' => CaseAttempt::class,
                'discussable_id' => $attempt->id,
                'persona' => $persona,
                'status' => DiscussionStatus::Active->value,
                'round_count' => 0,
                'max_rounds' => $this->resolveMaxRounds($attempt, $persona),
                'started_at' => now(),
            ]);

            $this->recordStudentTurn($session, $openingPosition, sequenceOrder: 1);

            $this->runAiTurn($session, $attempt, $personaInstance, history: [], newMessage: $openingPosition, sequenceOrder: 2);

            return $session->fresh();
        });
    }

    /**
     * §2.1: "Active (round N)" -> AI turn -> "Active (round N+1)" (loop) or
     * a terminal state, depending on the AI's verdict / whether max_rounds
     * is now reached.
     */
    public function respond(DiscussionSession $session, string $studentMessage): DiscussionSession
    {
        $this->ensureActive($session);

        /** @var CaseAttempt $attempt */
        $attempt = $session->discussable;
        $personaInstance = $this->personaResolver->resolve($session->persona);

        return DB::transaction(function () use ($session, $studentMessage, $attempt, $personaInstance) {
            $history = $this->buildConversationHistory($session);
            $nextSequence = ($session->turns()->max('sequence_order') ?? 0) + 1;

            $this->recordStudentTurn($session, $studentMessage, $nextSequence);

            $this->runAiTurn($session, $attempt, $personaInstance, $history, $studentMessage, $nextSequence + 1);

            return $session->fresh();
        });
    }

    /** §2.1: "Active" -> "Ended by Student", per the student's own action. */
    public function end(DiscussionSession $session): DiscussionSession
    {
        $this->ensureActive($session);

        $session->update([
            'status' => DiscussionStatus::EndedByStudent->value,
            'ended_at' => now(),
        ]);

        return $session->fresh();
    }

    private function runAiTurn(
        DiscussionSession $session,
        CaseAttempt $attempt,
        AiPersonaInterface $persona,
        array $history,
        string $newMessage,
        int $sequenceOrder,
    ): void {
        $subject = new CaseAttemptDiscussionSubject($attempt);
        $systemPrompt = $this->systemPromptBuilder->build($persona, $subject);

        $result = $this->llmClient->complete($systemPrompt, $history, $newMessage);

        if ($this->leakageGuard->containsLeak($result->replyText, $attempt->case->model_solution_summary ?? '')) {
            throw new LeakedReplyException(
                'AI reply blocked: contains leaked model-solution content. Turn was not persisted.'
            );
        }

        DiscussionTurn::create([
            'discussion_session_id' => $session->id,
            'sequence_order' => $sequenceOrder,
            'role' => DiscussionTurnRole::Ai->value,
            'content' => $result->replyText,
            'verdict' => $result->verdict->value,
            'internal_note' => $result->internalNote,
            'evidence_referenced' => $result->evidenceReferenced,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
            'provider' => $result->provider,
            'model' => $result->model,
        ]);

        $session->increment('round_count');
        $session->refresh();

        $this->applyStateTransition($session, $result->verdict);
    }

    /**
     * §2.1's four states. "accept" -> Accepted. "end_unresolved" (the AI
     * itself determining the discussion isn't converging) is treated the
     * same as reaching the round cap — MaxRoundsReached — since the schema
     * has no separate terminal status for it; noted here explicitly as an
     * interpretation, not an explicit part of the frozen design. "continue"
     * (and round cap not yet reached) leaves the session Active — no update
     * needed.
     */
    private function applyStateTransition(DiscussionSession $session, DiscussionVerdict $verdict): void
    {
        if ($verdict === DiscussionVerdict::Accept) {
            $session->update(['status' => DiscussionStatus::Accepted->value, 'ended_at' => now()]);

            return;
        }

        if ($verdict === DiscussionVerdict::EndUnresolved || $session->round_count >= $session->max_rounds) {
            $session->update(['status' => DiscussionStatus::MaxRoundsReached->value, 'ended_at' => now()]);
        }
    }

    private function recordStudentTurn(DiscussionSession $session, string $content, int $sequenceOrder): void
    {
        DiscussionTurn::create([
            'discussion_session_id' => $session->id,
            'sequence_order' => $sequenceOrder,
            'role' => DiscussionTurnRole::Student->value,
            'content' => $content,
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildConversationHistory(DiscussionSession $session): array
    {
        return $session->turns()
            ->orderBy('sequence_order')
            ->get()
            ->map(fn (DiscussionTurn $turn) => ['role' => $turn->role->value, 'content' => $turn->content])
            ->all();
    }

    private function resolveMaxRounds(CaseAttempt $attempt, string $persona): int
    {
        return $attempt->case->discussion_max_rounds
            ?? config("discussion_personas.{$persona}.default_max_rounds");
    }

    private function ensureNoActiveSessionExists(CaseAttempt $attempt): void
    {
        $exists = DiscussionSession::where('discussable_type', CaseAttempt::class)
            ->where('discussable_id', $attempt->id)
            ->where('status', DiscussionStatus::Active->value)
            ->exists();

        if ($exists) {
            throw new DiscussionAlreadyActiveException(
                'A discussion is already active for this attempt.'
            );
        }
    }

    private function ensureActive(DiscussionSession $session): void
    {
        if ($session->status !== DiscussionStatus::Active) {
            throw new DiscussionNotActiveException(
                "Cannot act on a discussion session in status \"{$session->status->value}\" — it must be active."
            );
        }
    }
}
