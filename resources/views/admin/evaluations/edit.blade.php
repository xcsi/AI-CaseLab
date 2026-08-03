@php
    $attempt = $evaluation->caseAttempt;
    $case = $attempt->case;
    $diagnosis = $evaluation->diagnosis;
    $formatScore = fn ($value) => \App\Support\ScoreFormatter::trim($value);
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fs-4 fw-semibold mb-0">Review — {{ $case->title }}</h2>
                <div class="text-secondary small mt-1">
                    {{ $attempt->user->name }} &middot; submitted {{ $diagnosis->submitted_at?->diffForHumans() }}
                </div>
            </div>
            <a href="{{ route('admin.evaluations.index') }}" class="btn btn-outline-secondary btn-sm">Back to Reviews</a>
        </div>
    </x-slot>

    <div class="container-fluid py-4" style="max-width: 960px;">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($evaluation->reviewed_at)
            <div class="alert alert-primary">
                Previously reviewed by {{ $evaluation->reviewer?->name ?? 'an instructor' }}
                {{ $evaluation->reviewed_at->diffForHumans() }}. Submitting again updates that review.
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header fw-semibold">Submitted Diagnosis</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-secondary small text-uppercase mb-1">Root Cause</div>
                    <p class="mb-0" style="white-space: pre-line;">{{ $diagnosis->root_cause_text }}</p>
                </div>
                <div class="mb-3">
                    <div class="text-secondary small text-uppercase mb-1">Proposed Fix</div>
                    <p class="mb-0" style="white-space: pre-line;">{{ $diagnosis->proposed_fix_text }}</p>
                </div>
                <div>
                    <div class="text-secondary small text-uppercase mb-1">Confidence</div>
                    <span class="badge text-bg-secondary">{{ ucfirst($diagnosis->confidence_level->value) }}</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.evaluations.update', $evaluation) }}">
            @csrf
            @method('PUT')

            <div class="card mb-4">
                <div class="card-header fw-semibold">Per-Criterion Scoring</div>
                <div class="card-body">
                    @foreach ($evaluation->criterionResults as $result)
                        <div class="pb-3 mb-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <div>
                                    <div class="fw-semibold">
                                        {{ $result->rubricCriterion->title }}
                                        @if ($result->isPendingManualReview())
                                            <span class="badge text-bg-warning ms-1">Pending</span>
                                        @endif
                                    </div>
                                    @if ($result->rubricCriterion->description)
                                        <div class="text-secondary small">{{ $result->rubricCriterion->description }}</div>
                                    @endif
                                    @if ($result->feedback_text)
                                        <div class="text-secondary small mt-1">Strategy note: {{ $result->feedback_text }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="row g-2 align-items-start">
                                <div class="col-auto" style="width: 140px;">
                                    <x-input-label for="criteria-{{ $result->id }}-score" value="Score" />
                                    <div class="input-group input-group-sm mt-1">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="{{ $result->max_score }}"
                                            id="criteria-{{ $result->id }}-score"
                                            name="criteria[{{ $result->id }}][score]"
                                            class="form-control @error('criteria.' . $result->id . '.score') is-invalid @enderror"
                                            value="{{ old('criteria.' . $result->id . '.score', $formatScore($result->effectiveScore())) }}"
                                            required
                                        >
                                        <span class="input-group-text">/ {{ $formatScore($result->max_score) }}</span>
                                    </div>
                                    <x-input-error :messages="$errors->get('criteria.' . $result->id . '.score')" class="mt-1" />
                                </div>
                                <div class="col">
                                    <x-input-label for="criteria-{{ $result->id }}-comment" value="Comment (optional)" />
                                    <textarea
                                        id="criteria-{{ $result->id }}-comment"
                                        name="criteria[{{ $result->id }}][comment]"
                                        rows="1"
                                        class="form-control form-control-sm mt-1"
                                    >{{ old('criteria.' . $result->id . '.comment', $result->instructor_comment) }}</textarea>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Overall Comment</div>
                <div class="card-body">
                    <textarea name="comment" rows="3" class="form-control @error('comment') is-invalid @enderror"
                        placeholder="Feedback for the student on the diagnosis as a whole (optional)">{{ old('comment', $evaluation->instructor_comment) }}</textarea>
                    <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Submit Review</button>
            <a href="{{ route('admin.evaluations.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</x-admin-layout>
