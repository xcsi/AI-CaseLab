<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHintRequest;
use App\Http\Requests\Admin\UpdateHintRequest;
use App\Models\CaseModel;
use App\Models\Hint;
use App\Services\HintService;
use Illuminate\Http\RedirectResponse;

class HintController extends Controller
{
    public function __construct(
        private readonly HintService $hints,
    ) {}

    public function store(StoreHintRequest $request, CaseModel $case): RedirectResponse
    {
        $this->hints->create($case, $request->validated());

        return back()->with('status', 'Hint added.');
    }

    public function update(UpdateHintRequest $request, Hint $hint): RedirectResponse
    {
        $this->hints->update($hint, $request->validated());

        return back()->with('status', 'Hint updated.');
    }

    public function destroy(Hint $hint): RedirectResponse
    {
        $this->authorize('update', $hint->case);

        $this->hints->delete($hint);

        return back()->with('status', 'Hint removed.');
    }

    public function moveUp(Hint $hint): RedirectResponse
    {
        $this->authorize('update', $hint->case);

        $this->hints->moveUp($hint);

        return back();
    }

    public function moveDown(Hint $hint): RedirectResponse
    {
        $this->authorize('update', $hint->case);

        $this->hints->moveDown($hint);

        return back();
    }
}
