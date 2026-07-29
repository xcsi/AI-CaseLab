<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreDiagnosisRequest;
use App\Models\CaseAttempt;
use App\Models\EvidenceView;
use App\Models\HintUnlock;
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
        if ($attempt->diagnosis()->exists()) {
            return redirect()->route('performance-review.show', $attempt);
        }

        $attempt->load('case.evidenceItems');

        return view('investigation.diagnosis', [
            'attempt' => $attempt,
            'viewedEvidenceItemIds' => EvidenceView::where('case_attempt_id', $attempt->id)
                ->pluck('evidence_item_id'),
            'hintsUsedCount' => HintUnlock::where('case_attempt_id', $attempt->id)->count(),
            'hintsPenaltyTotal' => HintUnlock::where('case_attempt_id', $attempt->id)->sum('penalty_applied'),
        ]);
    }

    public function store(StoreDiagnosisRequest $request, CaseAttempt $attempt): RedirectResponse
    {
        if (! $attempt->diagnosis()->exists()) {
            $this->diagnoses->submit($attempt, $request->validated());
        }

        return redirect()->route('performance-review.show', $attempt);
    }
}
