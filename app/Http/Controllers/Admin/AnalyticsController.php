<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Services\AnalyticsService;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function index(): View
    {
        // Read-only cross-cutting view, not its own resource — reuses
        // CasePolicy::viewAny() (admin or instructor) rather than adding a
        // new policy, matching DashboardController's precedent.
        $this->authorize('viewAny', CaseModel::class);

        return view('admin.analytics.index', [
            'summary' => $this->analytics->summary(),
            'categoryAggregates' => $this->analytics->categoryAggregates(),
        ]);
    }
}
