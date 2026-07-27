@php
    $difficultyBadge = fn ($difficulty) => match ($difficulty) {
        App\Enums\CaseDifficulty::Easy => 'text-bg-success',
        App\Enums\CaseDifficulty::Medium => 'text-bg-warning',
        App\Enums\CaseDifficulty::Hard => 'text-bg-danger',
        default => 'text-bg-secondary',
    };

    $scoreBadge = fn (float $percent) => match (true) {
        $percent < 50 => 'text-bg-danger',
        $percent < 75 => 'text-bg-warning',
        default => 'text-bg-success',
    };

    $selectedDifficulties = collect(request('difficulty', []));
    $isAuthed = auth()->check();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Assigned Incidents</h2>
    </x-slot>

    <div class="container py-4">
        <div class="d-md-none mb-3">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="offcanvas" data-bs-target="#incident-filters">
                Filters
            </button>
        </div>

        <form method="GET" action="{{ route('cases.index') }}" id="incident-filters-form">
            <div class="offcanvas-bottom offcanvas-md border-0" tabindex="-1" id="incident-filters" aria-labelledby="incident-filters-label">
                <div class="offcanvas-header d-md-none">
                    <h5 class="offcanvas-title" id="incident-filters-label">Filters</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-md-flex align-items-center flex-wrap gap-3 bg-white border rounded-3 p-3 mb-4">
                    <select name="category" class="form-select form-select-sm" style="max-width: 180px;" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <div class="btn-group" role="group" aria-label="Difficulty">
                        @foreach (App\Enums\CaseDifficulty::cases() as $difficulty)
                            <input type="checkbox" class="btn-check" name="difficulty[]" value="{{ $difficulty->value }}"
                                id="difficulty-{{ $difficulty->value }}" autocomplete="off"
                                @checked($selectedDifficulties->contains($difficulty->value)) onchange="this.form.submit()">
                            <label class="btn btn-sm btn-outline-secondary" for="difficulty-{{ $difficulty->value }}">
                                {{ ucfirst($difficulty->value) }}
                            </label>
                        @endforeach
                    </div>

                    @if ($isAuthed)
                        <select name="status" class="form-select form-select-sm" style="max-width: 170px;" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="not_started" @selected(request('status') === 'not_started')>Not Started</option>
                            <option value="in_progress" @selected(request('status') === 'in_progress')>In Progress</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </select>
                    @endif

                    <select name="sort" class="form-select form-select-sm" style="max-width: 170px;" onchange="this.form.submit()">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option>
                        <option value="difficulty" @selected(request('sort') === 'difficulty')>Difficulty</option>
                        <option value="title" @selected(request('sort') === 'title')>Title A&ndash;Z</option>
                    </select>

                    <div class="ms-md-auto" style="min-width: 220px;">
                        <input type="search" name="search" id="incident-search" class="form-control form-control-sm"
                            placeholder="Search incidents&hellip;" value="{{ request('search') }}">
                    </div>

                    @if (request()->anyFilled(['category', 'difficulty', 'status', 'search']) || request('sort', 'newest') !== 'newest')
                        <a href="{{ route('cases.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear Filters</a>
                    @endif
                </div>
            </div>
        </form>

        @if (! $anyPublishedCases)
            <div class="card shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    <p class="mb-0">New incidents are being triaged &mdash; check back soon.</p>
                </div>
            </div>
        @elseif ($cases->isEmpty())
            <div class="card shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    <p class="mb-3">No incidents match these filters.</p>
                    <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
                </div>
            </div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                @foreach ($cases as $case)
                    @php
                        $latestAttempt = $isAuthed ? $case->attempts->first() : null;
                    @endphp
                    <div class="col">
                        <a href="{{ route('cases.show', $case) }}" class="card shadow-sm h-100 text-decoration-none text-body">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge text-bg-light border">{{ $case->category->name }}</span>
                                    <span class="badge {{ $difficultyBadge($case->difficulty) }}">{{ ucfirst($case->difficulty->value) }}</span>
                                </div>
                                <h3 class="h6 fw-semibold mb-1">{{ $case->title }}</h3>
                                @if ($case->summary)
                                    <p class="text-secondary small mb-2">{{ Str::limit($case->summary, 90) }}</p>
                                @endif
                                <div class="text-secondary small mt-auto pt-2">
                                    {{ $case->estimated_minutes }} min

                                    @if ($latestAttempt && $latestAttempt->status === App\Enums\AttemptStatus::Completed)
                                        @php $percent = $latestAttempt->max_possible_score > 0 ? round($latestAttempt->score_earned / $latestAttempt->max_possible_score * 100) : 0; @endphp
                                        &middot; <span class="badge {{ $scoreBadge($percent) }}">{{ $percent }}% &#10003;</span>
                                    @elseif ($latestAttempt && $latestAttempt->status === App\Enums\AttemptStatus::InProgress)
                                        &middot; <span class="badge text-bg-primary">In Progress</span>
                                    @elseif ($isAuthed)
                                        &middot; <span class="text-secondary">Not started</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            {{ $cases->links('pagination::bootstrap-5') }}
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let searchTimeout;
            document.getElementById('incident-search').addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => this.form.submit(), 450);
            });
        });
    </script>
</x-app-layout>
