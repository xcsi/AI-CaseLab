<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
use App\Enums\CaseDifficulty;
use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaseCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $userId = auth()->id();

        $query = CaseModel::published()->with('category');

        if ($userId) {
            $query->with(['attempts' => fn ($q) => $q->where('user_id', $userId)->latest('updated_at')]);
        }

        if ($categoryId = $request->integer('category')) {
            $query->where('category_id', $categoryId);
        }

        $difficulties = collect($request->input('difficulty', []))
            ->filter(fn ($value) => in_array($value, array_column(CaseDifficulty::cases(), 'value'), true));

        if ($difficulties->isNotEmpty()) {
            $query->whereIn('difficulty', $difficulties->all());
        }

        if ($userId && $status = $request->string('status')->toString()) {
            match ($status) {
                'not_started' => $query->whereDoesntHave('attempts', fn ($q) => $q->where('user_id', $userId)),
                'in_progress' => $query->whereHas('attempts', fn ($q) => $q->where('user_id', $userId)->where('status', AttemptStatus::InProgress)),
                'completed' => $query->whereHas('attempts', fn ($q) => $q->where('user_id', $userId)->where('status', AttemptStatus::Completed)),
                default => null,
            };
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%"));
        }

        $this->applySort($query, $request->string('sort')->toString());

        $cases = $query->paginate(9)->withQueryString();

        return view('incidents.index', [
            'cases' => $cases,
            'categories' => Category::orderBy('name')->get(),
            'anyPublishedCases' => CaseModel::published()->exists(),
        ]);
    }

    public function show(CaseModel $case): View
    {
        if ($case->trashed() || $case->status !== CaseStatus::Published) {
            return view('incidents.unavailable');
        }

        $case->load(['category', 'hints']);

        $userId = auth()->id();
        $attempts = $userId
            ? $case->attempts()->where('user_id', $userId)->latest('updated_at')->get()
            : collect();

        return view('incidents.show', [
            'case' => $case,
            'latestAttempt' => $attempts->first(),
            'latestCompletedAttempt' => $attempts->firstWhere('status', AttemptStatus::Completed),
            'evidenceTypeCounts' => $case->evidenceItems()
                ->join('evidence_types', 'evidence_types.id', '=', 'evidence_items.evidence_type_id')
                ->selectRaw('evidence_types.label as label, count(*) as total')
                ->groupBy('evidence_types.label')
                ->pluck('total', 'label'),
        ]);
    }

    /**
     * @param  Builder<CaseModel>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'difficulty' => $query->orderByRaw("CASE difficulty WHEN 'easy' THEN 1 WHEN 'medium' THEN 2 WHEN 'hard' THEN 3 ELSE 4 END"),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('created_at'),
        };
    }
}
