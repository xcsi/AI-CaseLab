<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
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

        return view('investigation.performance-review', [
            'attempt' => $attempt,
            'caseAverageScore' => $this->caseAverageScore($attempt),
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
}
