@php
    $activityVerb = fn (string $action) => match ($action) {
        'created' => 'created',
        'updated' => 'edited',
        'published' => 'published',
        'archived' => 'archived',
        default => $action,
    };
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Dashboard</h2>
        <div class="text-secondary small">Case authoring overview</div>
    </x-slot>

    <div class="container-fluid py-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- Quick actions --}}
        <div class="d-flex flex-wrap gap-2 mb-4">
            @can('create', App\Models\CaseModel::class)
                <a href="{{ route('admin.cases.create') }}" class="btn btn-primary">Add Case</a>
            @endcan
            <a href="{{ route('admin.cases.index') }}" class="btn btn-outline-secondary">View All Cases</a>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Manage Categories</a>
        </div>

        {{-- Case status stat cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small text-uppercase">Draft Cases</div>
                                <div class="fs-2 fw-semibold">{{ $draftCount }}</div>
                            </div>
                            <span class="badge text-bg-secondary">Draft</span>
                        </div>
                        <div class="text-secondary small mt-2">Not yet visible to students.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small text-uppercase">Published Cases</div>
                                <div class="fs-2 fw-semibold">{{ $publishedCount }}</div>
                            </div>
                            <span class="badge text-bg-success">Published</span>
                        </div>
                        <div class="text-secondary small mt-2">Live in the student catalog.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small text-uppercase">Archived Cases</div>
                                <div class="fs-2 fw-semibold">{{ $archivedCount }}</div>
                            </div>
                            <span class="badge text-bg-dark">Archived</span>
                        </div>
                        <div class="text-secondary small mt-2">Removed from the catalog, restorable.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Needs attention --}}
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Needs Attention</span>
                        <span class="badge text-bg-warning">{{ $needsAttention->count() }}</span>
                    </div>
                    <div class="card-body p-0">
                        @forelse ($needsAttention as $entry)
                            <div class="d-flex justify-content-between align-items-start p-3 border-bottom">
                                <div>
                                    <a href="{{ route('admin.cases.edit', $entry['case']) }}" class="fw-semibold text-decoration-none">
                                        {{ $entry['case']->title }}
                                    </a>
                                    <ul class="text-danger small mb-0 mt-1 ps-3">
                                        @foreach ($entry['errors'] as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <a href="{{ route('admin.cases.edit', $entry['case']) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Fix Up</a>
                            </div>
                        @empty
                            <p class="text-secondary text-center py-4 mb-0">Nothing needs attention right now.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Case status overview --}}
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Case Status Overview</div>
                    <div class="card-body">
                        @if ($totalCases === 0)
                            <p class="text-secondary text-center py-4 mb-0">No cases created yet.</p>
                        @else
                            <div class="progress mb-3" style="height: 1.5rem" role="progressbar" aria-label="Case status breakdown">
                                @if ($draftCount > 0)
                                    <div class="progress-bar bg-secondary" style="width: {{ $draftCount / $totalCases * 100 }}%">{{ $draftCount }}</div>
                                @endif
                                @if ($publishedCount > 0)
                                    <div class="progress-bar bg-success" style="width: {{ $publishedCount / $totalCases * 100 }}%">{{ $publishedCount }}</div>
                                @endif
                                @if ($archivedCount > 0)
                                    <div class="progress-bar bg-dark" style="width: {{ $archivedCount / $totalCases * 100 }}%">{{ $archivedCount }}</div>
                                @endif
                            </div>
                            <dl class="row mb-0 small">
                                <dt class="col-8 fw-normal"><span class="badge text-bg-secondary">&nbsp;</span> Draft</dt>
                                <dd class="col-4 text-end">{{ $draftCount }} ({{ round($draftCount / $totalCases * 100) }}%)</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-success">&nbsp;</span> Published</dt>
                                <dd class="col-4 text-end">{{ $publishedCount }} ({{ round($publishedCount / $totalCases * 100) }}%)</dd>
                                <dt class="col-8 fw-normal"><span class="badge text-bg-dark">&nbsp;</span> Archived</dt>
                                <dd class="col-4 text-end">{{ $archivedCount }} ({{ round($archivedCount / $totalCases * 100) }}%)</dd>
                                <dt class="col-8 fw-semibold border-top pt-2 mt-2">Total</dt>
                                <dd class="col-4 text-end fw-semibold border-top pt-2 mt-2">{{ $totalCases }}</dd>
                            </dl>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Recent activity --}}
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Recent Activity</div>
                    <div class="card-body p-0">
                        @forelse ($recentActivity as $entry)
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom small">
                                <div><strong>{{ $entry->causer?->name ?? 'System' }}</strong> {{ $activityVerb($entry->action) }} case "{{ $entry->changes['title'] ?? 'Unknown' }}"</div>
                                <span class="text-secondary text-nowrap ms-2">{{ $entry->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <p class="text-secondary text-center py-4 mb-0">No activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Category statistics --}}
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Category Statistics</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Cases</th>
                                    <th class="text-end">Published</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categoryStats as $category)
                                    <tr>
                                        <td>{{ $category->name }}</td>
                                        <td class="text-end">{{ $category->cases_count }}</td>
                                        <td class="text-end">{{ $category->published_cases_count }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-secondary py-4">No categories yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
