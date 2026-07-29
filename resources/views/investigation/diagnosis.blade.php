@php
    $case = $attempt->case;
    $evidenceItems = $case->evidenceItems;
    $evidenceTotalCount = $evidenceItems->count();
    $evidenceViewedCount = $viewedEvidenceItemIds->count();
    $minutesSpent = (int) $attempt->started_at->diffInMinutes(now());
    $citedIds = old('cited_evidence_ids', $viewedEvidenceItemIds->all());

    $formatPenalty = fn ($value) => \App\Support\ScoreFormatter::trim($value);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Submit Diagnosis — {{ config('app.name', 'AI CaseLab') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body class="workspace-body-root">
        <div class="d-flex flex-column vh-100">
            {{-- Same collapsed-chrome treatment as the Workspace — closing
                 the ticket stays inside the focused investigation flow,
                 not a trip back into the global Engineering Office nav. --}}
            <header class="workspace-topbar d-flex align-items-center gap-3 px-3 py-2 border-bottom bg-white flex-shrink-0">
                <a href="{{ route('investigation.show', $attempt) }}" class="text-secondary text-decoration-none text-nowrap" title="Back to Workspace">
                    &larr; Back to Workspace
                </a>
                <span class="fw-semibold text-truncate min-w-0">Submit Diagnosis — {{ $case->title }}</span>
            </header>

            <main class="flex-grow-1 overflow-auto p-3 p-lg-4 pb-5">
                <form method="POST" action="{{ route('investigation.diagnosis.store', $attempt) }}" id="diagnosis-form" class="row g-4" style="max-width: 960px;">
                    @csrf

                    <div class="col-12 col-lg-8">
                        <div class="mb-4">
                            <label for="root_cause_text" class="form-label fw-semibold">Root Cause</label>
                            <textarea
                                name="root_cause_text"
                                id="root_cause_text"
                                rows="5"
                                class="form-control @error('root_cause_text') is-invalid @enderror"
                                required
                            >{{ old('root_cause_text') }}</textarea>
                            @error('root_cause_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="proposed_fix_text" class="form-label fw-semibold">Proposed Fix</label>
                            <textarea
                                name="proposed_fix_text"
                                id="proposed_fix_text"
                                rows="5"
                                class="form-control @error('proposed_fix_text') is-invalid @enderror"
                                required
                            >{{ old('proposed_fix_text') }}</textarea>
                            @error('proposed_fix_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <div class="form-label fw-semibold">Confidence</div>
                            <div class="btn-group" role="group" aria-label="Confidence level">
                                @foreach (\App\Enums\ConfidenceLevel::cases() as $level)
                                    <input
                                        type="radio"
                                        class="btn-check"
                                        name="confidence_level"
                                        id="confidence-{{ $level->value }}"
                                        value="{{ $level->value }}"
                                        autocomplete="off"
                                        required
                                        {{ old('confidence_level') === $level->value ? 'checked' : '' }}
                                    >
                                    <label class="btn btn-outline-secondary" for="confidence-{{ $level->value }}">{{ ucfirst($level->value) }}</label>
                                @endforeach
                            </div>
                            @error('confidence_level')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <div class="form-label fw-semibold">Evidence you relied on</div>
                            <div class="d-flex flex-wrap gap-2">
                                @forelse ($evidenceItems as $item)
                                    <input
                                        type="checkbox"
                                        class="btn-check"
                                        name="cited_evidence_ids[]"
                                        id="evidence-cite-{{ $item->id }}"
                                        value="{{ $item->id }}"
                                        autocomplete="off"
                                        {{ in_array($item->id, $citedIds) ? 'checked' : '' }}
                                    >
                                    <label class="btn btn-sm btn-outline-secondary" for="evidence-cite-{{ $item->id }}">{{ $item->title }}</label>
                                @empty
                                    <p class="text-secondary small mb-0">No evidence was added to this incident.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h2 class="h6 text-uppercase text-secondary mb-3">Recap</h2>
                                <dl class="small mb-4">
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <dt class="fw-normal text-secondary">Hints used</dt>
                                        <dd class="mb-0">{{ $hintsUsedCount }} (&minus;{{ $formatPenalty($hintsPenaltyTotal) }} pts)</dd>
                                    </div>
                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                        <dt class="fw-normal text-secondary">Time</dt>
                                        <dd class="mb-0">{{ $minutesSpent }} min</dd>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <dt class="fw-normal text-secondary">Evidence viewed</dt>
                                        <dd class="mb-0">{{ $evidenceViewedCount }}/{{ $evidenceTotalCount }}</dd>
                                    </div>
                                </dl>
                                <button type="button" class="btn btn-primary w-100 diagnosis-submit-trigger d-none d-lg-block">
                                    Submit Diagnosis
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>

            {{-- Mobile sticky submit bar — the form's real action, per the
                 approved UX spec's mobile behavior for this screen. --}}
            <div class="d-lg-none border-top bg-white p-2 flex-shrink-0">
                <button type="button" class="btn btn-primary w-100 diagnosis-submit-trigger">
                    Submit Diagnosis
                </button>
            </div>
        </div>

        {{-- Submit confirmation — final unless this incident allows reattempts. --}}
        <div class="modal fade" id="diagnosis-confirm" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Submit this diagnosis?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            @if ($case->allow_reattempt)
                                This closes the ticket for this attempt. Reattempts are allowed on this incident, so you can try again afterward.
                            @else
                                This is final — this incident does not allow reattempts.
                            @endif
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="diagnosis-form" class="btn btn-primary" id="diagnosis-confirm-submit">Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const form = document.getElementById('diagnosis-form');
                const confirmSubmit = document.getElementById('diagnosis-confirm-submit');

                document.querySelectorAll('.diagnosis-submit-trigger').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!form.reportValidity()) return;
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('diagnosis-confirm')).show();
                    });
                });

                // Disable on submit (regardless of which button triggered it)
                // so a double-click or a slow response can't double-post.
                form.addEventListener('submit', () => {
                    confirmSubmit.disabled = true;
                    confirmSubmit.textContent = 'Submitting…';
                    document.querySelectorAll('.diagnosis-submit-trigger').forEach((button) => {
                        button.disabled = true;
                    });
                });
            })();
        </script>
    </body>
</html>
