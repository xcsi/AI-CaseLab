@php
    $hints = $case->hints;
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Hints</span>
        @unless ($readOnly)
            <button type="button" class="btn btn-sm btn-primary" onclick="openCreateHintModal()">Add Hint</button>
        @endunless
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width: 1%">#</th>
                    <th>Content</th>
                    <th style="width: 10%">Penalty</th>
                    @unless ($readOnly)
                        <th class="text-end" style="width: 1%">Actions</th>
                    @endunless
                </tr>
            </thead>
            <tbody>
                @forelse ($hints as $index => $hint)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $hint->content }}</td>
                        <td>-{{ $hint->score_penalty }}</td>
                        @unless ($readOnly)
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('admin.hints.move-up', $hint) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Move up" title="Move up" @disabled($index === 0)><x-icon name="arrow-up" size="14" /></button>
                                </form>
                                <form method="POST" action="{{ route('admin.hints.move-down', $hint) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Move down" title="Move down" @disabled($index === $hints->count() - 1)><x-icon name="arrow-down" size="14" /></button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    onclick="openEditHintModal({{ $hint->id }}, @js($hint->content), {{ (float) $hint->score_penalty }})">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="openDeleteHintModal({{ $hint->id }}, @js(Str::limit($hint->content, 40)))">
                                    Delete
                                </button>
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-4">No hints yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@unless ($readOnly)
    {{-- Create/Edit modal --}}
    <div class="modal fade" id="hint-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="hint-form">
                @csrf
                <div id="hint-method-field"></div>
                <input type="hidden" name="id" id="hint-id" value="{{ old('id') }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="hint-modal-title">Add Hint</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <x-input-label for="hint-content" value="Content" />
                            <textarea class="form-control mt-1" id="hint-content" name="content" rows="3" required></textarea>
                            <x-input-error :messages="$errors->get('content')" class="mt-2" />
                        </div>
                        <div class="mb-3">
                            <x-input-label for="hint-score-penalty" value="Score Penalty" />
                            <div class="form-text mt-0 mb-1">Deducted from the achievable score when a student unlocks this hint.</div>
                            <x-text-input type="number" step="0.01" min="0" id="hint-score-penalty" name="score_penalty" class="mt-1" required />
                            <x-input-error :messages="$errors->get('score_penalty')" class="mt-2" />
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
    <div class="modal fade" id="delete-hint-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="delete-hint-form">
                @csrf
                @method('DELETE')
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Hint</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">Delete hint <strong id="delete-hint-name"></strong>? This cannot be undone.</p>
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
        function openCreateHintModal() {
            document.getElementById('hint-modal-title').textContent = 'Add Hint';
            document.getElementById('hint-form').action = '{{ route('admin.cases.hints.store', $case) }}';
            document.getElementById('hint-method-field').innerHTML = '';
            document.getElementById('hint-id').value = '';
            document.getElementById('hint-content').value = '';
            document.getElementById('hint-score-penalty').value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('hint-modal')).show();
        }

        function openEditHintModal(id, content, scorePenalty) {
            document.getElementById('hint-modal-title').textContent = 'Edit Hint';
            document.getElementById('hint-form').action = '/admin/hints/' + id;
            document.getElementById('hint-method-field').innerHTML = '<input type="hidden" name="_method" value="PUT">';
            document.getElementById('hint-id').value = id;
            document.getElementById('hint-content').value = content;
            document.getElementById('hint-score-penalty').value = scorePenalty;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('hint-modal')).show();
        }

        function openDeleteHintModal(id, label) {
            document.getElementById('delete-hint-form').action = '/admin/hints/' + id;
            document.getElementById('delete-hint-name').textContent = label;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('delete-hint-modal')).show();
        }

        @if ($errors->any() && old('score_penalty') !== null)
            document.addEventListener('DOMContentLoaded', () => {
                @if (old('id'))
                    openEditHintModal({{ old('id') }}, @js(old('content')), @js(old('score_penalty')));
                @else
                    openCreateHintModal();
                    document.getElementById('hint-content').value = @js(old('content'));
                    document.getElementById('hint-score-penalty').value = @js(old('score_penalty'));
                @endif
            });
        @endif
    </script>
@endunless
