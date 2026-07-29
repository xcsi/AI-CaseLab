@php
    $hasAnyActivity ??= false;

    $greeting = match (true) {
        now()->hour < 12 => 'Good morning',
        now()->hour < 17 => 'Good afternoon',
        default => 'Good evening',
    };

    $difficultyBadge = fn ($difficulty) => \App\Support\Badge::difficulty($difficulty);
    $scoreBadge = fn (float $percent) => \App\Support\Badge::score($percent);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Inbox</h2>
    </x-slot>

    <div class="container py-4">
        @unless ($hasAnyActivity)
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <h3 class="h5 fw-semibold mb-2">Your first incident is waiting</h3>
                    <p class="text-secondary mb-4">Head over to Assigned Incidents to pick up your first ticket.</p>
                    <a href="{{ route('cases.index') }}" class="btn btn-primary">Go to Assigned Incidents</a>
                </div>
            </div>
        @else
            <h1 class="h3 fw-semibold mb-3">{{ $greeting }}, {{ auth()->user()->name }}</h1>

            @if ($closedCount > 0)
                <div class="d-flex flex-nowrap overflow-auto gap-3 mb-4 pb-1">
                    <div class="card shadow-sm flex-shrink-0" style="min-width: 200px;">
                        <div class="card-body">
                            <div class="text-secondary small text-uppercase">Incidents Closed</div>
                            <div class="fs-2 fw-semibold">{{ $closedCount }}</div>
                        </div>
                    </div>
                    <div class="card shadow-sm flex-shrink-0" style="min-width: 200px;">
                        <div class="card-body">
                            <div class="text-secondary small text-uppercase">Average Score</div>
                            <div class="fs-2 fw-semibold">{{ $averageScore }}%</div>
                        </div>
                    </div>
                    <div class="card shadow-sm flex-shrink-0" style="min-width: 200px;">
                        <div class="card-body">
                            <div class="text-secondary small text-uppercase">Current Streak</div>
                            <div class="fs-2 fw-semibold">{{ $streak }} {{ Str::plural('day', $streak) }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row g-3 mb-4">
                @if ($continuing)
                    <div class="col-lg-8">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-secondary small text-uppercase mb-1">Continue Investigation</div>
                                <h3 class="h5 fw-semibold mb-1">{{ $continuing->case->title }}</h3>
                                <p class="text-secondary small mb-3">{{ $continuing->started_at->diffForHumans(null, true) }} elapsed</p>
                                <a href="{{ route('cases.index') }}" class="btn btn-primary">Resume</a>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="{{ $continuing ? 'col-lg-4' : 'col-lg-12' }}">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-secondary small text-uppercase mb-2">Recent Activity</div>
                            @forelse ($recentActivity as $attempt)
                                <div class="d-flex justify-content-between align-items-center py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                    <div>
                                        <div class="fw-semibold small">{{ $attempt->case->title }}</div>
                                        <div class="text-secondary small">
                                            @if ($attempt->status === App\Enums\AttemptStatus::Completed)
                                                closed {{ $attempt->completed_at?->diffForHumans() }}
                                            @else
                                                in progress &middot; started {{ $attempt->started_at->diffForHumans() }}
                                            @endif
                                        </div>
                                    </div>
                                    @if ($attempt->status === App\Enums\AttemptStatus::Completed && $attempt->max_possible_score > 0)
                                        @php $percent = round($attempt->score_earned / $attempt->max_possible_score * 100); @endphp
                                        <span class="badge {{ $scoreBadge($percent) }}">{{ $percent }}%</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-secondary small mb-0">Nothing here yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @if ($recommended)
                <div class="card shadow-sm">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="text-secondary small text-uppercase mb-1">Recommended Next</div>
                            <h3 class="h5 fw-semibold mb-0">
                                {{ $recommended->title }}
                                <span class="badge {{ $difficultyBadge($recommended->difficulty) }}">{{ ucfirst($recommended->difficulty->value) }}</span>
                            </h3>
                        </div>
                        <a href="{{ route('cases.index') }}" class="btn btn-outline-primary">View</a>
                    </div>
                </div>
            @endif
        @endunless
    </div>
</x-app-layout>
