@php
    $case = $attempt->case;
    $evaluation = $attempt->evaluation;
    $hasEvaluation = $evaluation !== null;

    $scorePercent = $hasEvaluation && $evaluation->max_score > 0
        ? round($evaluation->total_score / $evaluation->max_score * 100)
        : null;

    $scoreBadge = fn (float $percent) => match (true) {
        $percent < 50 => 'text-bg-danger',
        $percent < 75 => 'text-bg-warning',
        default => 'text-bg-success',
    };

    $criterionState = function ($result) {
        if ($result->max_score > 0 && $result->score_awarded >= $result->max_score) {
            return ['icon' => '&check;', 'class' => 'text-success'];
        }

        if ($result->score_awarded <= 0) {
            return ['icon' => '&#10007;', 'class' => 'text-danger'];
        }

        return ['icon' => '&#9680;', 'class' => 'text-warning'];
    };

    $canReattempt = $case->allow_reattempt;

    $formatScore = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Performance Review</h2>
        <div class="text-secondary small mt-1">{{ $case->title }}</div>
    </x-slot>

    <div class="container py-4 pb-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        @if ($hasEvaluation)
                            <div class="d-flex flex-wrap align-items-baseline gap-3">
                                <span style="font-size: 2rem;" class="fw-bold">
                                    {{ $formatScore($evaluation->total_score) }} / {{ $formatScore($evaluation->max_score) }}
                                </span>
                                <span class="badge {{ $scoreBadge($scorePercent) }}">{{ $scorePercent }}%</span>
                            </div>
                            @if ($caseAverageScore !== null)
                                <div class="text-secondary small mt-1">
                                    {{ $evaluation->total_score >= $caseAverageScore ? 'Above' : 'Below' }}
                                    case average ({{ $formatScore($caseAverageScore) }})
                                </div>
                            @endif
                        @else
                            <p class="text-secondary mb-0">
                                Diagnosis submitted &mdash; evaluation is still pending. Check back soon for your score and feedback.
                            </p>
                        @endif
                    </div>
                </div>

                @if ($hasEvaluation)
                    <div class="card shadow-sm mb-4">
                        <div class="card-header fw-semibold">Per-Criterion Breakdown</div>
                        <div class="card-body">
                            @foreach ($evaluation->criterionResults as $result)
                                @php $state = $criterionState($result); @endphp
                                <div class="py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="d-flex gap-2">
                                            <span class="{{ $state['class'] }}" aria-hidden="true">{!! $state['icon'] !!}</span>
                                            <span>{{ $result->rubricCriterion->title }}</span>
                                        </div>
                                        <span class="text-secondary text-nowrap">
                                            {{ $formatScore($result->score_awarded) }} / {{ $formatScore($result->max_score) }}
                                        </span>
                                    </div>
                                    @if ($result->feedback_text)
                                        <p class="text-secondary small mb-0 mt-1">{{ $result->feedback_text }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">What Actually Happened</div>
                    <div class="card-body">
                        @if ($case->model_solution_summary)
                            <p class="mb-0" style="white-space: pre-line;">{{ $case->model_solution_summary }}</p>
                        @else
                            <p class="text-secondary mb-0">No model solution summary has been added for this incident.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="d-none d-lg-flex flex-column gap-2">
                    <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary w-100">Back to Incidents</a>
                    @if ($canReattempt)
                        <form method="POST" action="{{ route('attempts.store', $case) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">Re-attempt</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary w-100" disabled title="This incident does not allow reattempts.">
                            Re-attempt
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-lg-none position-sticky bottom-0 bg-white border-top p-3 d-flex gap-2">
        <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary w-100">Back to Incidents</a>
        @if ($canReattempt)
            <form method="POST" action="{{ route('attempts.store', $case) }}" class="w-100">
                @csrf
                <button type="submit" class="btn btn-primary w-100">Re-attempt</button>
            </form>
        @else
            <button type="button" class="btn btn-outline-secondary w-100" disabled title="This incident does not allow reattempts.">
                Re-attempt
            </button>
        @endif
    </div>
</x-app-layout>
