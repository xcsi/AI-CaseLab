@php
    $criteria = $case->rubricCriteria;
    $totalWeight = $criteria->sum('weight');
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Rubric</span>
        @unless ($readOnly)
            <button type="button" class="btn btn-sm btn-primary" onclick="openCreateRubricModal()">Add Criterion</button>
        @endunless
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Title</th>
                    <th style="width: 15%">Matching Type</th>
                    <th>Details</th>
                    <th style="width: 10%">Weight</th>
                    @unless ($readOnly)
                        <th class="text-end" style="width: 1%">Actions</th>
                    @endunless
                </tr>
            </thead>
            <tbody>
                @forelse ($criteria as $criterion)
                    <tr data-weight="{{ $criterion->weight }}">
                        <td>{{ $criterion->title }}</td>
                        <td><span class="badge text-bg-secondary">{{ $criterion->matching_type->value }}</span></td>
                        <td class="text-secondary small">
                            @if ($criterion->matching_type === App\Enums\MatchingType::Keyword)
                                {{ implode(', ', $criterion->expected_data['keywords'] ?? []) }}
                            @elseif ($criterion->matching_type === App\Enums\MatchingType::EvidenceCitation)
                                Evidence citation setup available once evidence exists (Phase 7).
                            @else
                                Manually reviewed by an instructor.
                            @endif
                        </td>
                        <td>{{ $criterion->weight }}</td>
                        @unless ($readOnly)
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    onclick="openEditRubricModal(
                                        {{ $criterion->id }},
                                        @js($criterion->title),
                                        @js($criterion->description),
                                        {{ (float) $criterion->weight }},
                                        @js($criterion->matching_type->value),
                                        @js(implode(PHP_EOL, $criterion->expected_data['keywords'] ?? []))
                                    )">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="openDeleteRubricModal({{ $criterion->id }}, @js(Str::limit($criterion->title, 40)))">
                                    Delete
                                </button>
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-4">No rubric criteria yet.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($criteria->isNotEmpty())
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="3" class="text-end">Total weight (= this case's max score)</td>
                        <td id="rubric-total">{{ $totalWeight }}</td>
                        @unless ($readOnly)
                            <td></td>
                        @endunless
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@unless ($readOnly)
    {{-- Create/Edit modal --}}
    <div class="modal fade" id="rubric-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="rubric-form">
                @csrf
                <div id="rubric-method-field"></div>
                <input type="hidden" name="id" id="rubric-id" value="{{ old('id') }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rubric-modal-title">Add Criterion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <x-input-label for="rubric-title" value="Title" />
                            <x-text-input type="text" id="rubric-title" name="title" class="mt-1" required />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>
                        <div class="mb-3">
                            <x-input-label for="rubric-description" value="Description" />
                            <textarea class="form-control mt-1" id="rubric-description" name="description" rows="2"></textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                        <div class="mb-3">
                            <x-input-label for="rubric-matching-type" value="Matching Type" />
                            <select id="rubric-matching-type" name="matching_type" class="form-select mt-1" onchange="onRubricMatchingTypeChange()" required>
                                @foreach ($matchingTypes as $matchingType)
                                    <option value="{{ $matchingType->value }}">{{ $matchingType->value }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('matching_type')" class="mt-2" />
                        </div>
                        <div class="mb-3" id="rubric-keywords-field">
                            <x-input-label for="rubric-keywords" value="Keywords" />
                            <div class="form-text mt-0 mb-1">One keyword or phrase per line. A student's diagnosis is scored against these.</div>
                            <textarea class="form-control mt-1" id="rubric-keywords" name="keywords" rows="3"></textarea>
                            <x-input-error :messages="$errors->get('expected_data')" class="mt-2" />
                        </div>
                        <p class="text-secondary small mb-3" id="rubric-evidence-note" style="display: none;">
                            Evidence citation setup will be available once evidence items exist for this case (Phase 7).
                        </p>
                        <div class="mb-3">
                            <x-input-label for="rubric-weight" value="Weight" />
                            <x-text-input type="number" step="0.01" min="0.01" id="rubric-weight" name="weight" class="mt-1"
                                oninput="onRubricWeightInput()" required />
                            <x-input-error :messages="$errors->get('weight')" class="mt-2" />
                            <div class="form-text mt-1">Case max score after saving: <strong id="rubric-preview-total">{{ $totalWeight }}</strong></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-secondary-button data-bs-dismiss="modal">Cancel</x-secondary-button>
                        <x-primary-button>Save</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    <div class="modal fade" id="delete-rubric-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="delete-rubric-form">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Rubric Criterion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Delete criterion <strong id="delete-rubric-name"></strong>? This cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <x-secondary-button data-bs-dismiss="modal">Cancel</x-secondary-button>
                        <x-danger-button>Delete</x-danger-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        const rubricBaseTotal = {{ $totalWeight }};
        let rubricEditingWeight = 0;

        function onRubricMatchingTypeChange() {
            const type = document.getElementById('rubric-matching-type').value;
            document.getElementById('rubric-keywords-field').style.display = type === 'keyword' ? '' : 'none';
            document.getElementById('rubric-evidence-note').style.display = type === 'evidence_citation' ? '' : 'none';
        }

        function onRubricWeightInput() {
            const weight = parseFloat(document.getElementById('rubric-weight').value) || 0;
            const total = rubricBaseTotal - rubricEditingWeight + weight;
            document.getElementById('rubric-preview-total').textContent = Number.isFinite(total) ? total.toFixed(2) : rubricBaseTotal;
        }

        function openCreateRubricModal() {
            document.getElementById('rubric-modal-title').textContent = 'Add Criterion';
            document.getElementById('rubric-form').action = '{{ route('admin.cases.rubric-criteria.store', $case) }}';
            document.getElementById('rubric-method-field').innerHTML = '';
            document.getElementById('rubric-id').value = '';
            document.getElementById('rubric-title').value = '';
            document.getElementById('rubric-description').value = '';
            document.getElementById('rubric-matching-type').value = 'keyword';
            document.getElementById('rubric-keywords').value = '';
            document.getElementById('rubric-weight').value = '';
            rubricEditingWeight = 0;
            onRubricMatchingTypeChange();
            onRubricWeightInput();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('rubric-modal')).show();
        }

        function openEditRubricModal(id, title, description, weight, matchingType, keywords) {
            document.getElementById('rubric-modal-title').textContent = 'Edit Criterion';
            document.getElementById('rubric-form').action = '/admin/rubric-criteria/' + id;
            document.getElementById('rubric-method-field').innerHTML = '<input type="hidden" name="_method" value="PUT">';
            document.getElementById('rubric-id').value = id;
            document.getElementById('rubric-title').value = title;
            document.getElementById('rubric-description').value = description ?? '';
            document.getElementById('rubric-matching-type').value = matchingType;
            document.getElementById('rubric-keywords').value = keywords ?? '';
            document.getElementById('rubric-weight').value = weight;
            rubricEditingWeight = weight;
            onRubricMatchingTypeChange();
            onRubricWeightInput();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('rubric-modal')).show();
        }

        function openDeleteRubricModal(id, label) {
            document.getElementById('delete-rubric-form').action = '/admin/rubric-criteria/' + id;
            document.getElementById('delete-rubric-name').textContent = label;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('delete-rubric-modal')).show();
        }

        @if ($errors->any() && old('weight') !== null)
            document.addEventListener('DOMContentLoaded', () => {
                @if (old('id'))
                    openEditRubricModal(
                        {{ old('id') }},
                        @js(old('title')),
                        @js(old('description')),
                        {{ (float) old('weight', 0) }},
                        @js(old('matching_type')),
                        @js(old('keywords')),
                    );
                @else
                    openCreateRubricModal();
                    document.getElementById('rubric-title').value = @js(old('title'));
                    document.getElementById('rubric-description').value = @js(old('description'));
                    document.getElementById('rubric-matching-type').value = @js(old('matching_type'));
                    document.getElementById('rubric-keywords').value = @js(old('keywords'));
                    document.getElementById('rubric-weight').value = @js(old('weight'));
                    onRubricMatchingTypeChange();
                    onRubricWeightInput();
                @endif
            });
        @endif
    </script>
@endunless
