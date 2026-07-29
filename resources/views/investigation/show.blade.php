@php
    $case = $attempt->case;
    $groupedEvidence = $case->evidenceItems->groupBy(fn ($item) => $item->evidenceType->label);
    $evidenceTotalCount = $case->evidenceItems->count();
    $evidenceViewedCount = $viewedEvidenceItemIds->count();
    $orderedHints = $case->hints->sortBy('order_index')->values();

    $formatPenalty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<x-workspace-layout
    :title="$case->title"
    :exit-url="route('cases.show', $case)"
    :started-at="$attempt->started_at->toIso8601String()"
    :diagnosis-url="route('investigation.diagnosis.create', $attempt)"
    :evidence-viewed-count="$evidenceViewedCount"
    :evidence-total-count="$evidenceTotalCount"
>
    {{-- Phone gate: below 576px, the workspace opens read-only-by-default
         with an explicit override, per the approved UX spec. --}}
    <div id="workspace-phone-gate" class="d-sm-none p-3">
        <div class="alert alert-warning mb-0">
            <p class="mb-2">Best experienced on a larger screen.</p>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="workspace-phone-continue">
                Continue Anyway
            </button>
        </div>
    </div>

    <div id="workspace-content" class="d-none d-sm-flex flex-column h-100 min-h-0">
        {{-- Tab switcher: only meaningful below the lg breakpoint, where
             panes are tabbed instead of shown as 3 columns. --}}
        <div class="workspace-tabs d-lg-none border-bottom flex-shrink-0" role="tablist">
            <button type="button" class="btn btn-sm btn-link workspace-tab-btn active" data-target="ws-panel-evidence" role="tab" aria-selected="true">
                Evidence
            </button>
            <button type="button" class="btn btn-sm btn-link workspace-tab-btn" data-target="ws-panel-notes" role="tab" aria-selected="false">
                Notes
            </button>
        </div>

        <div class="d-flex flex-column flex-lg-row flex-grow-1 min-h-0">
            <div id="ws-panel-evidence" class="d-flex flex-column flex-lg-row flex-grow-1 min-h-0">
                <x-workspace-pane title="Evidence Explorer" style="flex: 0 0 260px;">
                    <div class="evidence-explorer">
                        <button type="button" class="evidence-explorer-item evidence-explorer-ticket active" data-id="ticket">
                            <span class="evidence-explorer-item-title">Support Ticket</span>
                        </button>

                        @forelse ($groupedEvidence as $groupLabel => $items)
                            <div class="evidence-explorer-group">
                                <div class="evidence-explorer-group-label">{{ $groupLabel }}</div>
                                @foreach ($items as $item)
                                    <button type="button" class="evidence-explorer-item" data-id="{{ $item->id }}">
                                        <span class="evidence-viewed-check {{ $viewedEvidenceItemIds->contains($item->id) ? '' : 'd-none' }}">&check;</span>
                                        <span class="evidence-explorer-item-title">{{ $item->title }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @empty
                            <p class="text-secondary small mt-3 mb-0">No other evidence has been added to this incident yet.</p>
                        @endforelse

                        @if ($orderedHints->isNotEmpty())
                            <div class="evidence-explorer-group hints-group">
                                <div class="evidence-explorer-group-label">Hints</div>

                                @foreach ($orderedHints as $hint)
                                    @php $unlock = $hintUnlocks->get($hint->id); @endphp
                                    <div class="hint-row {{ $unlock ? 'hint-row-unlocked' : '' }}" data-hint-id="{{ $hint->id }}">
                                        @if ($unlock)
                                            <div class="hint-row-header">
                                                <span class="hint-check">&check;</span>
                                                <span class="hint-label">Hint {{ $loop->iteration }}</span>
                                                <span class="badge text-bg-light hint-penalty-chip">&minus;{{ $formatPenalty($unlock->penalty_applied) }} pts</span>
                                            </div>
                                            <p class="hint-content small text-secondary mb-0">{{ $hint->content }}</p>
                                        @else
                                            <button
                                                type="button"
                                                class="hint-unlock-btn"
                                                data-hint-id="{{ $hint->id }}"
                                                data-penalty="{{ $hint->score_penalty }}"
                                                data-label="Hint {{ $loop->iteration }}"
                                            >
                                                <span class="hint-lock-icon" aria-hidden="true">&#128274;</span>
                                                <span class="hint-label">Hint {{ $loop->iteration }}</span>
                                                <span class="badge text-bg-light hint-penalty-chip ms-auto">&minus;{{ $formatPenalty($hint->score_penalty) }} pts</span>
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </x-workspace-pane>

                <x-workspace-pane title="Evidence Viewer" style="flex: 1 1 auto;" body-class="p-0 d-flex flex-column">
                    <div class="evidence-tab-bar d-flex align-items-center flex-wrap border-bottom flex-shrink-0 px-2" id="evidence-tab-bar">
                        <div class="evidence-tab-button active" data-tab-id="ticket">
                            <button type="button" class="evidence-tab-select">Support Ticket</button>
                        </div>
                    </div>

                    <div class="evidence-tab-content flex-grow-1 overflow-auto p-3">
                        <div class="evidence-tabpane" id="evidence-pane-ticket" data-title="Support Ticket">
                            @include('investigation.evidence.ticket', ['case' => $case])
                        </div>

                        @foreach ($case->evidenceItems as $item)
                            <div class="evidence-tabpane d-none" id="evidence-pane-{{ $item->id }}" data-title="{{ $item->title }}">
                                @include('investigation.evidence._dispatcher', ['evidenceItem' => $item])
                            </div>
                        @endforeach
                    </div>
                </x-workspace-pane>
            </div>

            <div id="ws-panel-notes" class="d-none d-lg-flex" style="flex: 0 0 320px;">
                <x-workspace-pane title="Engineering Notebook" class="flex-grow-1 border-end-0" body-class="p-0 d-flex flex-column">
                    <x-slot:headerActions>
                        <button type="button" class="btn btn-sm btn-link p-0 text-secondary" id="notebook-collapse" aria-label="Collapse Engineering Notebook" title="Collapse Engineering Notebook">
                            &raquo;
                        </button>
                    </x-slot:headerActions>

                    <textarea
                        id="notebook-textarea"
                        class="form-control flex-grow-1 evidence-notebook-textarea"
                        placeholder="Jot down what you notice — referenced evidence, suspicions, dead ends"
                    >{{ $attempt->investigationNote?->content }}</textarea>

                    <div class="px-3 py-2 border-top small text-secondary flex-shrink-0" id="notebook-status">
                        @if ($attempt->investigationNote?->updated_at)
                            Saved &middot; {{ $attempt->investigationNote->updated_at->format('H:i') }}
                        @endif
                    </div>
                </x-workspace-pane>
            </div>

            <button type="button" id="notebook-reopen" class="d-none align-items-center justify-content-center btn btn-sm btn-outline-secondary flex-shrink-0 evidence-notebook-reopen" aria-label="Expand Engineering Notebook" title="Expand Engineering Notebook">
                Engineering Notebook
            </button>
        </div>
    </div>

    {{-- Exit confirmation: only shown when the notebook has unsaved edits. --}}
    <div class="modal fade" id="notebook-exit-confirm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave without saving?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Your Engineering Notebook has changes that haven't finished saving yet.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Stay</button>
                    <button type="button" class="btn btn-danger" id="notebook-exit-confirm-leave">Leave Anyway</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Hint unlock confirmation: one shared modal, reused by every locked hint. --}}
    <div class="modal fade" id="hint-unlock-confirm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ask a senior engineer?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="hint-unlock-confirm-text"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="hint-unlock-confirm-proceed">Unlock Hint</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Screenshot lightbox: one shared modal, reused by every screenshot tab. --}}
    <div class="modal fade" id="evidence-lightbox" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center pt-0">
                    <img id="evidence-lightbox-image" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const attemptId = {{ $attempt->id }};
            const viewedIds = new Set(@json($viewedEvidenceItemIds->map(fn ($id) => (string) $id)));
            const openTabs = ['ticket'];
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Elapsed Timer (Milestone 5) — ticks from the attempt's
            // server-recorded started_at, so a reload just recomputes the
            // correct elapsed time instead of needing its own persistence.
            const timerEl = document.getElementById('workspace-timer');
            if (timerEl && timerEl.dataset.startedAt) {
                const startedAtMs = new Date(timerEl.dataset.startedAt).getTime();

                function formatElapsed(totalSeconds) {
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const seconds = totalSeconds % 60;
                    return [hours, minutes, seconds].map((n) => String(n).padStart(2, '0')).join(':');
                }

                function tickTimer() {
                    const elapsedSeconds = Math.max(0, Math.floor((Date.now() - startedAtMs) / 1000));
                    timerEl.textContent = `⏱ ${formatElapsed(elapsedSeconds)}`;
                }

                tickTimer();
                setInterval(tickTimer, 1000);
            }

            function updateProgressCounter() {
                const el = document.getElementById('workspace-progress');
                if (!el) return;
                const total = el.dataset.total;
                el.textContent = `${viewedIds.size}/${total} viewed`;
            }

            function recordView(evidenceItemId) {
                fetch(`/investigation/${attemptId}/evidence/${evidenceItemId}/view`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                }).then(() => {
                    if (!viewedIds.has(String(evidenceItemId))) {
                        viewedIds.add(String(evidenceItemId));
                        document.querySelector(`.evidence-explorer-item[data-id="${evidenceItemId}"] .evidence-viewed-check`)
                            ?.classList.remove('d-none');
                        updateProgressCounter();
                    }
                }).catch(() => {});
            }

            function activateTab(id) {
                document.querySelectorAll('.evidence-tabpane').forEach((pane) => {
                    pane.classList.toggle('d-none', pane.id !== 'evidence-pane-' + id);
                });
                document.querySelectorAll('.evidence-tab-button').forEach((button) => {
                    button.classList.toggle('active', button.dataset.tabId === id);
                });
                document.querySelectorAll('.evidence-explorer-item').forEach((item) => {
                    item.classList.toggle('active', item.dataset.id === id);
                });
            }

            function openTab(id) {
                if (!document.getElementById('evidence-pane-' + id)) return;

                if (!openTabs.includes(id)) {
                    openTabs.push(id);
                    const pane = document.getElementById('evidence-pane-' + id);
                    const tab = document.createElement('div');
                    tab.className = 'evidence-tab-button';
                    tab.dataset.tabId = id;
                    tab.innerHTML = `<button type="button" class="evidence-tab-select">${pane.dataset.title}</button>`
                        + `<button type="button" class="evidence-tab-close" aria-label="Close tab">&times;</button>`;
                    document.getElementById('evidence-tab-bar').appendChild(tab);
                }

                activateTab(id);

                if (id !== 'ticket') {
                    recordView(id);
                }
            }

            function closeTab(id) {
                if (id === 'ticket') return;

                const index = openTabs.indexOf(id);
                if (index !== -1) openTabs.splice(index, 1);
                document.querySelector(`.evidence-tab-button[data-tab-id="${id}"]`)?.remove();

                const wasActive = document.getElementById('evidence-pane-' + id)?.classList.contains('d-none') === false;
                if (wasActive) {
                    activateTab(openTabs[openTabs.length - 1] || 'ticket');
                }
            }

            document.getElementById('evidence-tab-bar').addEventListener('click', (event) => {
                const closeButton = event.target.closest('.evidence-tab-close');
                const selectButton = event.target.closest('.evidence-tab-select');
                if (closeButton) {
                    closeTab(closeButton.closest('.evidence-tab-button').dataset.tabId);
                } else if (selectButton) {
                    activateTab(selectButton.closest('.evidence-tab-button').dataset.tabId);
                }
            });

            document.querySelectorAll('.evidence-explorer-item').forEach((item) => {
                item.addEventListener('click', () => openTab(item.dataset.id));
            });

            document.querySelectorAll('.evidence-log-filter').forEach((input) => {
                input.addEventListener('input', function () {
                    const target = document.getElementById(this.dataset.target);
                    const query = this.value.toLowerCase();
                    target.querySelectorAll('.evidence-log-line').forEach((line) => {
                        line.classList.toggle('d-none', query !== '' && !line.textContent.toLowerCase().includes(query));
                    });
                });
            });

            document.querySelectorAll('.evidence-toggle').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById(button.dataset.target)?.classList.toggle('d-none');
                });
            });

            document.querySelectorAll('[data-lightbox-trigger]').forEach((image) => {
                image.addEventListener('click', () => {
                    document.getElementById('evidence-lightbox-image').src = image.src;
                    document.getElementById('evidence-lightbox-image').alt = image.alt;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('evidence-lightbox')).show();
                });
            });

            // Engineering Notebook — debounced autosave (Milestone 3).
            const notebookTextarea = document.getElementById('notebook-textarea');
            const notebookStatus = document.getElementById('notebook-status');
            const notesPanel = document.getElementById('ws-panel-notes');
            const notebookReopen = document.getElementById('notebook-reopen');
            let notebookDebounceTimer = null;
            let notebookSaveInFlight = false;
            let notebookSavePending = false;
            let notebookDirty = false;
            let notebookRetryCount = 0;

            function formatSavedAt(iso) {
                return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function saveNotebook() {
                if (notebookSaveInFlight) {
                    notebookSavePending = true;
                    return;
                }

                notebookSaveInFlight = true;
                notebookStatus.textContent = 'Saving…';

                fetch(`/investigation/${attemptId}/notes`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ content: notebookTextarea.value }),
                })
                    .then((response) => {
                        if (!response.ok) throw new Error('notebook save failed');
                        return response.json();
                    })
                    .then((data) => {
                        notebookSaveInFlight = false;
                        notebookRetryCount = 0;
                        notebookDirty = false;
                        notebookStatus.textContent = `Saved · ${formatSavedAt(data.saved_at)}`;

                        if (notebookSavePending) {
                            notebookSavePending = false;
                            saveNotebook();
                        }
                    })
                    .catch(() => {
                        notebookSaveInFlight = false;
                        notebookRetryCount += 1;

                        if (notebookRetryCount <= 3) {
                            setTimeout(saveNotebook, 1500 * notebookRetryCount);
                        } else {
                            notebookStatus.textContent = "Couldn't save your note — retrying…";
                            setTimeout(() => {
                                notebookRetryCount = 0;
                                saveNotebook();
                            }, 8000);
                        }
                    });
            }

            notebookTextarea?.addEventListener('input', () => {
                notebookDirty = true;
                clearTimeout(notebookDebounceTimer);
                notebookDebounceTimer = setTimeout(saveNotebook, 800);
            });

            document.getElementById('notebook-collapse')?.addEventListener('click', () => {
                notesPanel.classList.add('d-none');
                notesPanel.classList.remove('d-lg-flex');
                notebookReopen.classList.remove('d-none');
                notebookReopen.classList.add('d-lg-flex');
            });

            notebookReopen?.addEventListener('click', () => {
                notesPanel.classList.remove('d-none');
                notesPanel.classList.add('d-lg-flex');
                notebookReopen.classList.add('d-none');
                notebookReopen.classList.remove('d-lg-flex');
            });

            document.getElementById('workspace-exit-link')?.addEventListener('click', (event) => {
                if (!notebookDirty) return;
                event.preventDefault();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('notebook-exit-confirm')).show();
            });

            document.getElementById('notebook-exit-confirm-leave')?.addEventListener('click', () => {
                window.location.href = document.getElementById('workspace-exit-link').href;
            });

            // Hint Unlocking (Milestone 4).
            let pendingHintButton = null;

            function formatPenaltyDisplay(value) {
                const num = parseFloat(value);
                return Number.isInteger(num) ? String(num) : String(num);
            }

            document.querySelectorAll('.hint-unlock-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    pendingHintButton = button;
                    document.getElementById('hint-unlock-confirm-text').textContent =
                        `This will reduce your max score by ${formatPenaltyDisplay(button.dataset.penalty)} pts — continue?`;
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('hint-unlock-confirm')).show();
                });
            });

            document.getElementById('hint-unlock-confirm-proceed')?.addEventListener('click', function () {
                if (!pendingHintButton) return;

                const button = pendingHintButton;
                const hintId = button.dataset.hintId;
                const hintLabel = button.dataset.label;
                const proceedButton = this;

                button.disabled = true;
                proceedButton.disabled = true;
                proceedButton.textContent = 'Unlocking…';

                fetch(`/investigation/${attemptId}/hints/${hintId}/unlock`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        const row = button.closest('.hint-row');
                        row.classList.add('hint-row-unlocked');
                        row.innerHTML = `
                            <div class="hint-row-header">
                                <span class="hint-check">&check;</span>
                                <span class="hint-label"></span>
                                <span class="badge text-bg-light hint-penalty-chip">&minus;${formatPenaltyDisplay(data.penalty_applied)} pts</span>
                            </div>
                            <p class="hint-content small text-secondary mb-0"></p>
                        `;
                        row.querySelector('.hint-label').textContent = hintLabel;
                        row.querySelector('.hint-content').textContent = data.content;

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('hint-unlock-confirm')).hide();
                    })
                    .catch(() => {
                        button.disabled = false;
                    })
                    .finally(() => {
                        proceedButton.disabled = false;
                        proceedButton.textContent = 'Unlock Hint';
                        pendingHintButton = null;
                    });
            });

            document.getElementById('workspace-phone-continue')?.addEventListener('click', () => {
                document.getElementById('workspace-phone-gate').classList.add('d-none');
                document.getElementById('workspace-content').classList.remove('d-none');
                document.getElementById('workspace-content').classList.add('d-flex');
            });

            document.querySelectorAll('.workspace-tab-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('.workspace-tab-btn').forEach((b) => {
                        b.classList.remove('active');
                        b.setAttribute('aria-selected', 'false');
                    });
                    button.classList.add('active');
                    button.setAttribute('aria-selected', 'true');

                    const target = button.dataset.target;
                    document.getElementById('ws-panel-evidence').classList.toggle('d-none', target !== 'ws-panel-evidence');
                    document.getElementById('ws-panel-notes').classList.toggle('d-none', target !== 'ws-panel-notes');
                    document.getElementById('ws-panel-notes').classList.toggle('d-flex', target === 'ws-panel-notes');
                });
            });
        })();
    </script>
</x-workspace-layout>
