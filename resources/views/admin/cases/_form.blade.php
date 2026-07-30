@php
    $case ??= null;
    $readOnly ??= false;
    $publishErrors ??= [];
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Basic Information</div>
            <div class="card-body">
                <div class="mb-3">
                    <x-input-label for="case-title" value="Title" />
                    <x-text-input type="text" id="case-title" name="title" class="mt-1"
                        value="{{ old('title', $case?->title) }}" required :disabled="$readOnly" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-slug" value="Slug" />
                    <x-text-input type="text" id="case-slug" name="slug" class="mt-1"
                        value="{{ old('slug', $case?->slug) }}" required :disabled="$readOnly" />
                    <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-category" value="Category" />
                    <select id="case-category" name="category_id" class="form-select mt-1" required @disabled($readOnly)>
                        <option value="">Select a category&hellip;</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $case?->category_id) == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-summary" value="Summary" />
                    <div class="form-text mt-0 mb-1">Short teaser shown on the catalog card.</div>
                    <textarea id="case-summary" name="summary" class="form-control" rows="2" @disabled($readOnly)>{{ old('summary', $case?->summary) }}</textarea>
                    <x-input-error :messages="$errors->get('summary')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-ticket-content" value="Ticket Content" />
                    <div class="form-text mt-0 mb-1">The initial support ticket shown to the student.</div>
                    <textarea id="case-ticket-content" name="ticket_content" class="form-control" rows="6" required @disabled($readOnly)>{{ old('ticket_content', $case?->ticket_content) }}</textarea>
                    <x-input-error :messages="$errors->get('ticket_content')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-learning-outcomes" value="Learning Outcomes" />
                    <div class="form-text mt-0 mb-1">Admin-facing for now; not yet shown to students.</div>
                    <textarea id="case-learning-outcomes" name="learning_outcomes" class="form-control" rows="3" @disabled($readOnly)>{{ old('learning_outcomes', $case?->learning_outcomes) }}</textarea>
                    <x-input-error :messages="$errors->get('learning_outcomes')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-model-solution-summary" value="Expected Diagnosis" />
                    <div class="form-text mt-0 mb-1">Revealed to students after completion, subject to the reattempt policy.</div>
                    <textarea id="case-model-solution-summary" name="model_solution_summary" class="form-control" rows="4" @disabled($readOnly)>{{ old('model_solution_summary', $case?->model_solution_summary) }}</textarea>
                    <x-input-error :messages="$errors->get('model_solution_summary')" class="mt-2" />
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Settings</div>
            <div class="card-body">
                <div class="mb-3">
                    <x-input-label for="case-difficulty" value="Difficulty" />
                    <select id="case-difficulty" name="difficulty" class="form-select mt-1" required @disabled($readOnly)>
                        @foreach ($difficulties as $difficulty)
                            <option value="{{ $difficulty->value }}" @selected(old('difficulty', $case?->difficulty?->value) === $difficulty->value)>
                                {{ ucfirst($difficulty->value) }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('difficulty')" class="mt-2" />
                </div>

                <div class="mb-3">
                    <x-input-label for="case-estimated-minutes" value="Estimated Minutes" />
                    <x-text-input type="number" id="case-estimated-minutes" name="estimated_minutes" class="mt-1"
                        min="1" value="{{ old('estimated_minutes', $case?->estimated_minutes) }}" required :disabled="$readOnly" />
                    <x-input-error :messages="$errors->get('estimated_minutes')" class="mt-2" />
                </div>

                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="case-allow-reattempt" name="allow_reattempt" value="1"
                        @checked(old('allow_reattempt', $case?->allow_reattempt ?? true)) @disabled($readOnly)>
                    <label class="form-check-label" for="case-allow-reattempt">Allow reattempt</label>
                </div>

                @if ($case)
                    <hr>
                    <dl class="row mb-0 small text-secondary">
                        <dt class="col-6">Status</dt>
                        <dd class="col-6 text-end text-capitalize">{{ $case->status->value }}</dd>
                        <dt class="col-6">Version</dt>
                        <dd class="col-6 text-end">{{ $case->version }}</dd>
                        <dt class="col-6">Max Score</dt>
                        <dd class="col-6 text-end">{{ $case->max_score }}</dd>
                    </dl>
                    <div class="form-text mt-2">Status, version, and max score are managed automatically (publishing and rubric weighting are handled elsewhere).</div>
                @endif
            </div>
        </div>

        {{-- Engineering Discussion (docs/13 §11.3, Phase 19 Milestone 2) —
             grouped near the Publish-workflow controls below, following
             this form's existing conventions: the allow-reattempt
             checkbox pattern above, and the estimated-minutes number
             input pattern for the numeric override. --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold">Engineering Discussion</div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="case-discussion-enabled" name="discussion_enabled" value="1"
                        @checked(old('discussion_enabled', $case?->discussion_enabled ?? false)) @disabled($readOnly)>
                    <label class="form-check-label" for="case-discussion-enabled">Enable Engineering Discussion</label>
                </div>

                <div class="mb-3">
                    <x-input-label for="case-discussion-default-persona" value="Default Persona" />
                    <select id="case-discussion-default-persona" name="discussion_default_persona" class="form-select mt-1" @disabled($readOnly)>
                        <option value="">Select a persona&hellip;</option>
                        @foreach (config('discussion_personas') as $personaKey => $personaConfig)
                            <option value="{{ $personaKey }}" @selected(old('discussion_default_persona', $case?->discussion_default_persona) === $personaKey)>
                                {{ $personaConfig['display_name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text mt-0 mb-1">Required to enable Engineering Discussion.</div>
                    <x-input-error :messages="$errors->get('discussion_default_persona')" class="mt-2" />
                </div>

                <div class="mb-0">
                    <x-input-label for="case-discussion-max-rounds" value="Max Rounds Override" />
                    <div class="form-text mt-0 mb-1">Leave blank to use the selected persona's default.</div>
                    <x-text-input type="number" id="case-discussion-max-rounds" name="discussion_max_rounds" class="mt-1"
                        min="1" value="{{ old('discussion_max_rounds', $case?->discussion_max_rounds) }}" :disabled="$readOnly" />
                    <x-input-error :messages="$errors->get('discussion_max_rounds')" class="mt-2" />
                </div>
            </div>
        </div>

        @if ($case && ! $readOnly && $case->status === App\Enums\CaseStatus::Draft)
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Publish</div>
                <div class="card-body">
                    @if ($publishErrors)
                        <ul class="text-danger small mb-3 ps-3">
                            @foreach ($publishErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-secondary small mb-3">This case is ready to publish.</p>
                    @endif
                    {{-- Submits the standalone #publish-form below via the HTML5 `form`
                         attribute, since this card renders inside #case-form and HTML
                         doesn't allow nested <form> elements. --}}
                    <button type="submit" form="publish-form" class="btn btn-success" @disabled($publishErrors)>Publish</button>
                </div>
            </div>
        @endif

        @unless ($readOnly)
            <x-primary-button>{{ $case ? 'Save Changes' : 'Create Case' }}</x-primary-button>
            <a href="{{ route('admin.cases.index') }}" class="btn btn-link">Cancel</a>
        @endunless
    </div>
</div>
