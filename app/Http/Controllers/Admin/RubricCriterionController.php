<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRubricCriterionRequest;
use App\Http\Requests\Admin\UpdateRubricCriterionRequest;
use App\Models\CaseModel;
use App\Models\RubricCriterion;
use Illuminate\Http\RedirectResponse;

class RubricCriterionController extends Controller
{
    public function store(StoreRubricCriterionRequest $request, CaseModel $case): RedirectResponse
    {
        $case->rubricCriteria()->create($request->validated());
        $case->recalculateMaxScore();
        $case->touchVersionIfPublished();

        return back()->with('status', 'Rubric criterion added.');
    }

    public function update(UpdateRubricCriterionRequest $request, RubricCriterion $rubricCriterion): RedirectResponse
    {
        $rubricCriterion->update($request->validated());
        $rubricCriterion->case->recalculateMaxScore();
        $rubricCriterion->case->touchVersionIfPublished();

        return back()->with('status', 'Rubric criterion updated.');
    }

    public function destroy(RubricCriterion $rubricCriterion): RedirectResponse
    {
        $this->authorize('update', $rubricCriterion->case);

        $case = $rubricCriterion->case;
        $rubricCriterion->delete();
        $case->recalculateMaxScore();
        $case->touchVersionIfPublished();

        return back()->with('status', 'Rubric criterion removed.');
    }
}
