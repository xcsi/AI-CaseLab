<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseDifficulty;
use App\Enums\CaseStatus;
use App\Enums\MatchingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCaseRequest;
use App\Http\Requests\Admin\UpdateCaseRequest;
use App\Models\CaseModel;
use App\Models\Category;
use App\Services\CaseCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseCatalogService $caseCatalog,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', CaseModel::class);

        $cases = CaseModel::with('category')->orderByDesc('updated_at')->get();

        return view('admin.cases.index', compact('cases'));
    }

    public function create(): View
    {
        $this->authorize('create', CaseModel::class);

        return view('admin.cases.create', [
            'categories' => Category::orderBy('name')->get(),
            'difficulties' => CaseDifficulty::cases(),
        ]);
    }

    public function store(StoreCaseRequest $request): RedirectResponse
    {
        $case = $this->caseCatalog->create($request->validated(), $request->user());

        return redirect()->route('admin.cases.edit', $case)->with('status', 'Case created.');
    }

    public function edit(CaseModel $case): View
    {
        $this->authorize('viewAny', CaseModel::class);

        return view('admin.cases.edit', [
            'case' => $case,
            'categories' => Category::orderBy('name')->get(),
            'difficulties' => CaseDifficulty::cases(),
            'matchingTypes' => MatchingType::cases(),
            'publishErrors' => $case->status === CaseStatus::Draft
                ? $this->caseCatalog->publishInvariantErrors($case)
                : [],
        ]);
    }

    public function update(UpdateCaseRequest $request, CaseModel $case): RedirectResponse
    {
        $this->caseCatalog->update($case, $request->validated());

        return redirect()->route('admin.cases.edit', $case)->with('status', 'Case updated.');
    }

    public function destroy(CaseModel $case): RedirectResponse
    {
        $this->authorize('delete', $case);

        $case->delete();

        return redirect()->route('admin.cases.index')->with('status', 'Case archived.');
    }

    public function publish(CaseModel $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $errors = $this->caseCatalog->publish($case);

        if ($errors !== []) {
            return back()->withErrors(['publish' => $errors]);
        }

        return redirect()->route('admin.cases.edit', $case)->with('status', 'Case published.');
    }
}
