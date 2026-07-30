<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Models\Evaluation;
use App\Services\EvaluationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerformanceReviewController extends Controller
{
    public function __construct(
        private readonly EvaluationService $evaluations,
    ) {}

    public function show(CaseAttempt $attempt): View|RedirectResponse
    {
        $diagnosis = $attempt->diagnosis()->first();

        if (! $diagnosis) {
            return redirect()->route('investigation.show', $attempt);
        }

        // Backfill: an attempt submitted before the Evaluation Engine
        // existed (or any other path that reached here without one) is
        // evaluated now, on first view, instead of showing "pending"
        // forever with no way to ever clear it.
        if (! $attempt->evaluation()->exists()) {
            $this->evaluations->evaluate($attempt, $diagnosis);
        }

        $attempt->load(['case', 'diagnosis', 'evaluation.criterionResults.rubricCriterion']);

        $discussionSession = $this->latestDiscussionSession($attempt);

        return view('investigation.performance-review', [
            'attempt' => $attempt,
            'caseAverageScore' => $this->caseAverageScore($attempt),
            'discussionSession' => $discussionSession,
            'discussionTurns' => $discussionSession?->turns()->orderBy('sequence_order')->get() ?? collect(),
        ]);
    }

    /**
     * Only shown when at least one other evaluated attempt on this case
     * exists — comparing a score against itself isn't a meaningful
     * "case average" (per the approved UX spec, this comparison is
     * optional).
     */
    private function caseAverageScore(CaseAttempt $attempt): ?float
    {
        if (! $attempt->evaluation) {
            return null;
        }

        $scores = Evaluation::whereHas(
            'caseAttempt',
            fn ($query) => $query->where('case_id', $attempt->case_id)
        )->pluck('total_score');

        return $scores->count() > 1 ? round((float) $scores->avg(), 1) : null;
    }

    /**
     * §11.2's "a completed discussion is visible after the fact" — the
     * most recent session for this attempt, regardless of outcome (a
     * student can end without accepting, or hit the round cap, and still
     * want to see it here), or null when no discussion was ever started
     * ("no discussion at all" per this milestone's test matrix).
     */
    private function latestDiscussionSession(CaseAttempt $attempt): ?DiscussionSession
    {
        return DiscussionSession::where('discussable_type', CaseAttempt::class)
            ->where('discussable_id', $attempt->id)
            ->latest('started_at')
            ->first();
    }
}
