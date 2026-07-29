<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEvaluationReviewRequest;
use App\Models\Evaluation;
use App\Services\ManualReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EvaluationReviewController extends Controller
{
    public function __construct(
        private readonly ManualReviewService $manualReview,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Evaluation::class);

        $evaluations = Evaluation::awaitingInstructorReview()
            ->with(['caseAttempt.case', 'caseAttempt.user'])
            ->orderBy('evaluated_at')
            ->get();

        return view('admin.evaluations.index', compact('evaluations'));
    }

    public function edit(Evaluation $evaluation): View
    {
        $this->authorize('review', $evaluation);

        $evaluation->load([
            'caseAttempt.case',
            'caseAttempt.user',
            'diagnosis',
            'criterionResults.rubricCriterion',
            'reviewer',
        ]);

        return view('admin.evaluations.edit', compact('evaluation'));
    }

    public function update(UpdateEvaluationReviewRequest $request, Evaluation $evaluation): RedirectResponse
    {
        $this->manualReview->submitReview(
            $evaluation,
            $request->user(),
            $request->validated('criteria'),
            $request->validated('comment'),
        );

        return redirect()->route('admin.evaluations.index')->with('status', 'Review submitted.');
    }
}
