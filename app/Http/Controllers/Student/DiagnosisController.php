<?php

namespace App\Http\Controllers\Student;

use App\Enums\DiscussionStatus;
use App\Enums\DiscussionTurnRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreDiagnosisRequest;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Services\DiagnosisSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiagnosisController extends Controller
{
    public function __construct(
        private readonly DiagnosisSubmissionService $diagnoses,
    ) {}

    public function create(CaseAttempt $attempt): View|RedirectResponse
    {
        if ($this->alreadySubmitted($attempt)) {
            return redirect()->route('performance-review.show', $attempt);
        }

        $attempt->load('case.evidenceItems');

        return view('investigation.diagnosis', [
            'attempt' => $attempt,
            'viewedEvidenceItemIds' => $attempt->evidenceViews()->pluck('evidence_item_id'),
            'hintsUsedCount' => $attempt->hintUnlocks()->count(),
            'hintsPenaltyTotal' => $attempt->hintUnlocks()->sum('penalty_applied'),
            'acceptedDiscussionPosition' => $this->acceptedDiscussionPosition($attempt),
        ]);
    }

    public function store(StoreDiagnosisRequest $request, CaseAttempt $attempt): RedirectResponse
    {
        if (! $this->alreadySubmitted($attempt)) {
            $this->diagnoses->submit($attempt, $request->validated());
        }

        return redirect()->route('performance-review.show', $attempt);
    }

    private function alreadySubmitted(CaseAttempt $attempt): bool
    {
        return $attempt->diagnosis()->exists();
    }

    /**
     * The accept -> diagnosis-prefill flow (docs/13 §11.1, Phase 18
     * Milestone 3). Reads the accepted discussion's final student turn
     * directly, rather than parsing it back out of
     * discussion_sessions.outcome_summary's human-readable recap sentence
     * (PrefillDiagnosisFromAcceptedDiscussion) — the same render-time
     * read, not write-in-advance, approach that listener's own docblock
     * already anticipated for this form. Null when no discussion was
     * accepted for this attempt, the ordinary case for most attempts.
     */
    private function acceptedDiscussionPosition(CaseAttempt $attempt): ?string
    {
        $session = DiscussionSession::where('discussable_type', CaseAttempt::class)
            ->where('discussable_id', $attempt->id)
            ->where('status', DiscussionStatus::Accepted->value)
            ->latest('started_at')
            ->first();

        if (! $session) {
            return null;
        }

        return $session->turns()
            ->where('role', DiscussionTurnRole::Student->value)
            ->orderByDesc('sequence_order')
            ->value('content');
    }
}
