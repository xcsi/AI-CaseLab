@php
    $difficultyBadge = fn ($difficulty) => \App\Support\Badge::difficulty($difficulty);

    $priorityLabel = match ($case->difficulty) {
        App\Enums\CaseDifficulty::Easy => 'Low',
        App\Enums\CaseDifficulty::Medium => 'Medium',
        App\Enums\CaseDifficulty::Hard => 'High',
        default => 'Medium',
    };

    $isAuthed = auth()->check();

    $ctaLabel = 'Start Investigation';
    $showStartButton = true;
    $showViewReportLink = false;

    if ($latestAttempt?->status === App\Enums\AttemptStatus::InProgress) {
        $ctaLabel = 'Resume Investigation';
    } elseif ($latestCompletedAttempt) {
        $showViewReportLink = true;

        if ($case->allow_reattempt) {
            $ctaLabel = 'Start New Investigation';
        } else {
            $showStartButton = false;
        }
    }

    $pastScorePercent = $latestCompletedAttempt && $latestCompletedAttempt->max_possible_score > 0
        ? round($latestCompletedAttempt->score_earned / $latestCompletedAttempt->max_possible_score * 100)
        : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h2 class="fs-4 fw-semibold mb-0">{{ $case->title }}</h2>
            <span class="badge text-bg-light border">{{ $case->category->name }}</span>
            <span class="badge {{ $difficultyBadge($case->difficulty) }}">{{ ucfirst($case->difficulty->value) }}</span>
            <span class="text-secondary small">{{ $case->estimated_minutes }} min</span>
        </div>
        @if ($pastScorePercent !== null)
            <div class="text-secondary small mt-1">
                You closed this incident before &mdash; {{ $pastScorePercent }}% ({{ $latestCompletedAttempt->completed_at?->diffForHumans() }})
            </div>
        @endif
    </x-slot>

    <div class="container py-4 pb-5">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Support Ticket</span>
                        <span class="badge {{ $difficultyBadge($case->difficulty) }}">Priority: {{ $priorityLabel }}</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-0" style="white-space: pre-line;">{{ $case->ticket_content }}</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header fw-semibold">What You'll Investigate</div>
                    <div class="card-body">
                        @forelse ($evidenceTypeCounts as $label => $count)
                            <div class="d-flex justify-content-between small py-1">
                                <span>{{ $label }}</span>
                                <span class="text-secondary">{{ $count }}</span>
                            </div>
                        @empty
                            <p class="text-secondary small mb-0">Evidence for this incident hasn't been attached yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header fw-semibold">Scoring</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between small py-1">
                            <span>Max score</span>
                            <span class="text-secondary">{{ $case->max_score }} pts</span>
                        </div>
                        <div class="d-flex justify-content-between small py-1">
                            <span>Hints</span>
                            <span class="text-secondary">
                                {{ $case->hints->isNotEmpty() ? $case->hints->count().' available, cost points when used' : 'None for this incident' }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between small py-1">
                            <span>Reattempts</span>
                            <span class="text-secondary">{{ $case->allow_reattempt ? 'Allowed' : 'Not allowed' }}</span>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-block">
                    @if (! $isAuthed)
                        <a href="{{ route('login') }}" class="btn btn-primary w-100">Log In to Start</a>
                    @else
                        @if ($showStartButton)
                            <form method="POST" action="{{ route('attempts.store', $case) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">{{ $ctaLabel }}</button>
                            </form>
                        @endif
                        @if ($showViewReportLink)
                            <a href="{{ route('performance-review.show', $latestCompletedAttempt) }}" class="btn btn-outline-secondary w-100 {{ $showStartButton ? 'mt-2' : '' }}">
                                View Past Report
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-lg-none position-sticky bottom-0 bg-white border-top p-3">
        @if (! $isAuthed)
            <a href="{{ route('login') }}" class="btn btn-primary w-100">Log In to Start</a>
        @else
            @if ($showStartButton)
                <form method="POST" action="{{ route('attempts.store', $case) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">{{ $ctaLabel }}</button>
                </form>
            @endif
            @if ($showViewReportLink)
                <a href="{{ route('performance-review.show', $latestCompletedAttempt) }}" class="btn btn-outline-secondary w-100 {{ $showStartButton ? 'mt-2' : '' }}">
                    View Past Report
                </a>
            @endif
        @endif
    </div>
</x-app-layout>
