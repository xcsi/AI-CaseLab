<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreDiagnosisRequest;
use App\Models\CaseAttempt;
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
}
