<?php

namespace App\Http\Controllers\Student;

use App\Enums\CaseStatus;
use App\Exceptions\ReattemptNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\EvidenceView;
use App\Models\HintUnlock;
use App\Services\CaseAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CaseAttemptController extends Controller
{
    public function __construct(
        private readonly CaseAttemptService $attempts,
    ) {}

    public function store(CaseModel $case): RedirectResponse
    {
        abort_unless($case->status === CaseStatus::Published, 404);

        try {
            $attempt = $this->attempts->start($case, auth()->user());
        } catch (ReattemptNotAllowedException) {
            return redirect()->route('cases.show', $case)
                ->with('error', 'This incident does not allow reattempts.');
        }

        return redirect()->route('investigation.show', $attempt);
    }

    public function show(CaseAttempt $attempt): View
    {
        $attempt->load(['case.evidenceItems.evidenceType', 'case.hints', 'investigationNote']);

        return view('investigation.show', [
            'attempt' => $attempt,
            'viewedEvidenceItemIds' => EvidenceView::where('case_attempt_id', $attempt->id)
                ->pluck('evidence_item_id'),
            'hintUnlocks' => HintUnlock::where('case_attempt_id', $attempt->id)
                ->get()
                ->keyBy('hint_id'),
        ]);
    }
}
