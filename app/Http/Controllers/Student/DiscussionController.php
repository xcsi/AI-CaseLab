<?php

namespace App\Http\Controllers\Student;

use App\Discussion\Exceptions\DiscussionAlreadyActiveException;
use App\Discussion\Exceptions\DiscussionNotActiveException;
use App\Discussion\Exceptions\LeakedReplyException;
use App\Discussion\Exceptions\NoLlmProviderAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RespondToDiscussionRequest;
use App\Http\Requests\Student\StartDiscussionRequest;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Services\DiscussionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Exposes DiscussionService over HTTP (docs/13-ai-discussion-engine-design.md
 * §10), per docs/14-v2-implementation-roadmap.md's Phase 17 Milestone 1.
 * Validates (Form Requests), authorizes (attempt.owner middleware +
 * DiscussionSessionPolicy), delegates to DiscussionService, and returns
 * typed JSON — no business logic, no state-machine decisions here; every
 * one of those already lives in DiscussionService and stays there
 * unchanged.
 */
class DiscussionController extends Controller
{
    public function __construct(
        private readonly DiscussionService $discussions,
    ) {}

    public function start(StartDiscussionRequest $request, CaseAttempt $attempt): JsonResponse
    {
        try {
            $session = $this->discussions->start(
                $attempt,
                $request->validated('opening_position'),
                $request->validated('persona'),
            );
        } catch (DiscussionAlreadyActiveException $e) {
            return $this->conflict($e->getMessage());
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        } catch (NoLlmProviderAvailableException $e) {
            $this->logChainExhausted($e, $attempt);

            return $this->unavailable();
        } catch (LeakedReplyException) {
            return $this->unavailable();
        }

        return $this->sessionResponse($session);
    }

    public function show(CaseAttempt $attempt): JsonResponse
    {
        $session = $this->findSession($attempt);

        if (! $session) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $this->authorize('view', $session);

        return $this->sessionResponse($session);
    }

    public function respond(RespondToDiscussionRequest $request, CaseAttempt $attempt): JsonResponse
    {
        $session = $this->findSession($attempt);
        abort_unless($session, 404);
        $this->authorize('participate', $session);

        try {
            $session = $this->discussions->respond($session, $request->validated('message'));
        } catch (DiscussionNotActiveException $e) {
            return $this->conflict($e->getMessage());
        } catch (NoLlmProviderAvailableException $e) {
            $this->logChainExhausted($e, $attempt, $session);

            return $this->unavailable();
        } catch (LeakedReplyException) {
            return $this->unavailable();
        }

        return $this->sessionResponse($session);
    }

    public function end(CaseAttempt $attempt): JsonResponse
    {
        $session = $this->findSession($attempt);
        abort_unless($session, 404);
        $this->authorize('participate', $session);

        try {
            $session = $this->discussions->end($session);
        } catch (DiscussionNotActiveException $e) {
            return $this->conflict($e->getMessage());
        }

        return $this->sessionResponse($session);
    }

    /**
     * There's no {session} route parameter (docs/13 §10's route table is
     * scoped entirely by {attempt}) — the relevant session is always
     * derived from the already attempt.owner-verified attempt, never
     * accepted as a client-supplied ID.
     */
    private function findSession(CaseAttempt $attempt): ?DiscussionSession
    {
        return DiscussionSession::where('discussable_type', CaseAttempt::class)
            ->where('discussable_id', $attempt->id)
            ->latest('started_at')
            ->first();
    }

    private function sessionResponse(DiscussionSession $session): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'session' => [
                'id' => $session->id,
                'status' => $session->status->value,
                'persona' => $session->persona,
                'round_count' => $session->round_count,
                'max_rounds' => $session->max_rounds,
            ],
            'turns' => $session->turns()->orderBy('sequence_order')->get()->map(fn ($turn) => [
                'role' => $turn->role->value,
                'content' => $turn->content,
                'verdict' => $turn->verdict?->value,
                'sequence_order' => $turn->sequence_order,
            ]),
        ]);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], 409);
    }

    /**
     * Deliberately the same generic response for both chain exhaustion and
     * a blocked leaking reply — a student should never learn *why* (§11.5:
     * no mention of providers, paid/free, or safety internals in
     * student-facing copy). Exact wording is Phase 18's UI job; this is
     * just the typed status this milestone owns.
     */
    private function unavailable(): JsonResponse
    {
        return response()->json([
            'status' => 'unavailable',
            'message' => 'AI Discussion is temporarily unavailable. This does not affect your investigation or your ability to submit a diagnosis.',
        ], 503);
    }

    /**
     * Structured application logging on full chain exhaustion (§1.4.5,
     * Phase 20 Milestone 2) — for operational visibility only, never
     * surfaced to the student (that's §11.5's job, already handled by
     * unavailable()'s generic response above). Deliberately scoped to
     * NoLlmProviderAvailableException specifically, not LeakedReplyException
     * — a leak block is a different event class this milestone doesn't
     * cover. The exception's own message already names which tiers were
     * tried and why each failed (ChainedLlmClient, Phase 14/20).
     */
    private function logChainExhausted(
        NoLlmProviderAvailableException $e,
        CaseAttempt $attempt,
        ?DiscussionSession $session = null,
    ): void {
        Log::warning('Engineering Discussion: LLM fallback chain exhausted.', [
            'attempt_id' => $attempt->id,
            'case_id' => $attempt->case_id,
            'discussion_session_id' => $session?->id,
            'persona' => $session?->persona,
            'reason' => $e->getMessage(),
        ]);
    }
}
