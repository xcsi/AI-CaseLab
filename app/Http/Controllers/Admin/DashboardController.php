<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CaseModel;
use App\Models\Category;
use App\Services\CaseCatalogService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CaseCatalogService $caseCatalog,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', CaseModel::class);

        $draftCount = CaseModel::where('status', CaseStatus::Draft)->count();
        $publishedCount = CaseModel::where('status', CaseStatus::Published)->count();
        $archivedCount = CaseModel::onlyTrashed()->count();

        return view('admin.dashboard', [
            'draftCount' => $draftCount,
            'publishedCount' => $publishedCount,
            'archivedCount' => $archivedCount,
            'totalCases' => $draftCount + $publishedCount + $archivedCount,
            'needsAttention' => $this->needsAttention(),
            'recentActivity' => ActivityLog::with('causer')->latest('created_at')->take(10)->get(),
            'categoryStats' => Category::withCount([
                'cases',
                'cases as published_cases_count' => fn ($query) => $query->where('status', CaseStatus::Published),
            ])->orderByDesc('cases_count')->take(8)->get(),
        ]);
    }

    /**
     * Draft cases that would fail CaseCatalogService::publish() right now,
     * paired with the specific reasons — reuses the same invariant check
     * the Publish button's disabled-state relies on, so this list never
     * drifts from what actually blocks publishing.
     *
     * @return Collection<int, array{case: CaseModel, errors: array<int, string>}>
     */
    private function needsAttention(): Collection
    {
        return CaseModel::where('status', CaseStatus::Draft)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (CaseModel $case) => [
                'case' => $case,
                'errors' => $this->caseCatalog->publishInvariantErrors($case),
            ])
            ->filter(fn (array $entry) => $entry['errors'] !== [])
            ->take(8)
            ->values();
    }
}
