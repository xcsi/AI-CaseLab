@php
    $difficultyBadge = fn ($difficulty) => \App\Support\Badge::difficulty($difficulty);
    $scoreBadge = fn (float $percent) => \App\Support\Badge::score($percent);

    $isAuthed = auth()->check();
@endphp

<p class="visually-hidden" data-incident-status>
    @if (! $anyPublishedCases)
        No incidents available yet.
    @elseif ($cases->isEmpty())
        No incidents match these filters.
    @else
        Showing {{ $cases->firstItem() }}&ndash;{{ $cases->lastItem() }} of {{ $cases->total() }} incidents.
    @endif
</p>

@if (! $anyPublishedCases)
    <div class="card">
        <div class="card-body">
            <x-empty-state message="New incidents are being triaged — check back soon." />
        </div>
    </div>
@elseif ($cases->isEmpty())
    <div class="card">
        <div class="card-body">
            <x-empty-state
                message="No incidents match these filters."
                action-url="{{ route('cases.index') }}"
                action-label="Clear Filters"
            />
        </div>
    </div>
@else
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
        @foreach ($cases as $case)
            @php
                $latestAttempt = $isAuthed ? $case->attempts->first() : null;
            @endphp
            <div class="col">
                <a href="{{ route('cases.show', $case) }}" class="card h-100 text-decoration-none text-body">
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
                                &middot; <span class="badge {{ $scoreBadge($percent) }}">{{ $percent }}% <x-icon name="check" size="11" /></span>
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
