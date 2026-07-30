@php
    $case = $attempt->case;
    $groupedEvidence = $case->evidenceItems->groupBy(fn ($item) => $item->evidenceType->label);
    $evidenceTotalCount = $case->evidenceItems->count();
    $evidenceViewedCount = $viewedEvidenceItemIds->count();
    $orderedHints = $case->hints->sortBy('order_index')->values();

    $formatPenalty = fn ($value) => \App\Support\ScoreFormatter::trim($value);
@endphp

<x-workspace-layout
    :title="$case->title"
    :exit-url="route('cases.show', $case)"
    :started-at="$attempt->started_at->toIso8601String()"
    :diagnosis-url="route('investigation.diagnosis.create', $attempt)"
    :evidence-viewed-count="$evidenceViewedCount"
    :evidence-total-count="$evidenceTotalCount"
    :discussion-url="$case->discussion_enabled ? route('investigation.discussion.start', $attempt) : null"
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
                        maxlength="20000"
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
                    <p class="text-danger small mb-0 mt-2 d-none" id="hint-unlock-confirm-error">Couldn't unlock this hint &mdash; please try again.</p>
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

    {{-- Engineering Discussion panel (Version 2, Phase 18). A Bootstrap
         modal reusing the workspace's existing dark-panel visual language
         (docs/13 §11.1), the same register the evidence log/code viewers
         already establish, rather than a generic light chat-bubble UI.
         Gated on discussion_enabled, the same condition the entry point
         button (Milestone 1) already uses, so a disabled case's markup
         stays byte-for-byte what it was before Version 2 existed. --}}
    @if ($case->discussion_enabled)
    <div class="modal fade" id="discussion-panel-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content discussion-panel">
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title mb-0">Engineering Discussion</h5>
                        <span class="small discussion-round-counter" id="discussion-round-counter"></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        {{-- Always available while the discussion is active
                             (§11.1) — hidden once the session is no longer
                             active, since there's nothing left to end. --}}
                        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="discussion-end-button">End Discussion</button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body pt-0">
                    <p class="small discussion-error d-none" id="discussion-error"></p>

                    {{-- "AI Discussion Unavailable" state (§11.5, Milestone
                         4) — the fallback chain exhausted, not a validation
                         error (#discussion-error above, .text-danger red)
                         and not a silently-retried network failure (the
                         Notebook's autosave pattern) — a plain, neutral,
                         specific message instead of a chat bubble, with a
                         "Try Again" action the student triggers themselves
                         rather than an automatic retry loop. The message
                         text itself always comes from the server response
                         (DiscussionController's already-generic copy) —
                         never hardcoded here — so no provider name can ever
                         reach this template. --}}
                    <div class="discussion-unavailable d-none" id="discussion-unavailable">
                        <p class="mb-2" id="discussion-unavailable-message"></p>
                        <button type="button" class="btn btn-outline-light btn-sm" id="discussion-unavailable-retry">Try Again</button>
                    </div>

                    <div class="discussion-transcript" id="discussion-transcript"></div>

                    {{-- Accept -> diagnosis-prefill transition moment (§11.1,
                         Milestone 3) — a deliberate call to action, not a
                         silent redirect, so the student understands the
                         discussion just fed into the next step. The
                         diagnosis form itself pre-fills the accepted
                         position server-side (DiagnosisController::create). --}}
                    <div class="discussion-outcome-banner d-none" id="discussion-accepted-banner">
                        <p class="mb-2">Your position was accepted. Continue to your diagnosis &mdash; it will open with your accepted position pre-filled, and you can still edit it before submitting.</p>
                        <a href="{{ route('investigation.diagnosis.create', $attempt) }}" class="btn btn-success btn-sm">Continue to Diagnosis</a>
                    </div>

                    <p class="small discussion-thinking d-none" id="discussion-thinking">AI is thinking&hellip;</p>

                    <form id="discussion-open-form" class="d-none">
                        <label for="discussion-open-input" class="form-label small">
                            State your position &mdash; what's your read on this incident so far?
                        </label>
                        <textarea id="discussion-open-input" class="form-control discussion-input" rows="3" maxlength="5000" required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm mt-2" id="discussion-open-submit">Begin Discussion</button>
                    </form>

                    <form id="discussion-reply-form" class="d-none d-flex gap-2 mt-2">
                        <textarea id="discussion-reply-input" class="form-control discussion-input flex-grow-1" rows="2" maxlength="5000" placeholder="Respond&hellip;" required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm align-self-end" id="discussion-reply-submit">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- End Discussion confirmation, mirroring the existing
         exit-confirmation modal pattern (#notebook-exit-confirm). Cancel
         and Close reopen the discussion panel modal explicitly (rather
         than data-bs-dismiss) since this confirmation is triggered from
         inside the already-open discussion panel modal — Bootstrap
         doesn't natively chain two simultaneously-open modals, so this
         hide-then-show handoff is Bootstrap's own documented pattern for
         moving between modals. --}}
    <div class="modal fade" id="discussion-end-confirm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">End this discussion?</h5>
                    <button type="button" class="btn-close" id="discussion-end-confirm-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">You won't be able to continue this Engineering Discussion after ending it.</p>
                    <p class="text-danger small mb-0 mt-2 d-none" id="discussion-end-confirm-error">Couldn't end the discussion &mdash; please try again.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="discussion-end-confirm-cancel">Keep Discussing</button>
                    <button type="button" class="btn btn-danger" id="discussion-end-confirm-proceed">End Discussion</button>
                </div>
            </div>
        </div>
    </div>
    @endif

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

                    // Built via textContent, not innerHTML, since the title
                    // is evidence-item data rather than a static string.
                    const selectButton = document.createElement('button');
                    selectButton.type = 'button';
                    selectButton.className = 'evidence-tab-select';
                    selectButton.textContent = pane.dataset.title;

                    const closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'evidence-tab-close';
                    closeButton.setAttribute('aria-label', 'Close tab');
                    closeButton.innerHTML = '&times;';

                    tab.append(selectButton, closeButton);
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
                return String(parseFloat(value));
            }

            document.querySelectorAll('.hint-unlock-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    pendingHintButton = button;
                    document.getElementById('hint-unlock-confirm-text').textContent =
                        `This will reduce your max score by ${formatPenaltyDisplay(button.dataset.penalty)} pts — continue?`;
                    document.getElementById('hint-unlock-confirm-error').classList.add('d-none');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('hint-unlock-confirm')).show();
                });
            });

            document.getElementById('hint-unlock-confirm-proceed')?.addEventListener('click', function () {
                if (!pendingHintButton) return;

                const button = pendingHintButton;
                const hintId = button.dataset.hintId;
                const hintLabel = button.dataset.label;
                const proceedButton = this;
                const errorEl = document.getElementById('hint-unlock-confirm-error');

                button.disabled = true;
                proceedButton.disabled = true;
                proceedButton.textContent = 'Unlocking…';
                errorEl.classList.add('d-none');

                fetch(`/investigation/${attemptId}/hints/${hintId}/unlock`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                })
                    .then((response) => {
                        if (!response.ok) throw new Error('hint unlock failed');
                        return response.json();
                    })
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
                        errorEl.classList.remove('d-none');
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

            @if ($case->discussion_enabled)
            // Engineering Discussion panel (Version 2, Phase 18). Mirrors
            // the Notebook autosave / Hint unlock fetch conventions above:
            // CSRF header, .then/.catch chains, disabled-state handling
            // while a request is in flight. #discussion-error is a minimal
            // fallback for generic/network failures only — the dedicated
            // "AI Discussion Unavailable" state (chain exhausted, §11.5)
            // is #discussion-unavailable, further below. The whole block
            // is server-side gated (not just the runtime
            // `if (discussionStartButton)` check below) so a
            // disabled case's page source contains none of these element
            // IDs at all, matching the existing entry point's guarantee.
            const discussionStartButton = document.getElementById('workspace-discussion-start-button');

            if (discussionStartButton) {
                const discussionUrl = discussionStartButton.dataset.discussionStartUrl;
                const discussionMessagesUrl = `/investigation/${attemptId}/discussion/messages`;
                const discussionEndUrl = `/investigation/${attemptId}/discussion/end`;

                const discussionModalEl = document.getElementById('discussion-panel-modal');
                const discussionRoundCounter = document.getElementById('discussion-round-counter');
                const discussionTranscript = document.getElementById('discussion-transcript');
                const discussionThinking = document.getElementById('discussion-thinking');
                const discussionError = document.getElementById('discussion-error');
                const discussionUnavailable = document.getElementById('discussion-unavailable');
                const discussionUnavailableMessage = document.getElementById('discussion-unavailable-message');
                const discussionAcceptedBanner = document.getElementById('discussion-accepted-banner');
                const discussionOpenForm = document.getElementById('discussion-open-form');
                const discussionOpenInput = document.getElementById('discussion-open-input');
                const discussionOpenSubmit = document.getElementById('discussion-open-submit');
                const discussionReplyForm = document.getElementById('discussion-reply-form');
                const discussionReplyInput = document.getElementById('discussion-reply-input');
                const discussionReplySubmit = document.getElementById('discussion-reply-submit');
                const discussionEndButton = document.getElementById('discussion-end-button');
                const discussionEndConfirmEl = document.getElementById('discussion-end-confirm');
                const discussionEndConfirmError = document.getElementById('discussion-end-confirm-error');
                const discussionEndConfirmProceed = document.getElementById('discussion-end-confirm-proceed');

                function renderDiscussionTurns(turns) {
                    discussionTranscript.innerHTML = '';
                    turns.forEach((turn) => {
                        const row = document.createElement('div');
                        row.className = `discussion-turn discussion-turn-${turn.role}`;

                        const bubble = document.createElement('div');
                        bubble.className = 'discussion-turn-bubble';
                        bubble.textContent = turn.content;

                        row.appendChild(bubble);
                        discussionTranscript.appendChild(row);
                    });
                    discussionTranscript.scrollTop = discussionTranscript.scrollHeight;
                }

                function applyDiscussionSession(data) {
                    hideDiscussionUnavailable();
                    discussionRoundCounter.textContent = `Round ${data.session.round_count} of ${data.session.max_rounds}`;
                    renderDiscussionTurns(data.turns);

                    const active = data.session.status === 'active';
                    discussionOpenForm.classList.add('d-none');
                    discussionReplyForm.classList.toggle('d-none', !active);
                    discussionReplyInput.disabled = !active;
                    discussionReplySubmit.disabled = !active;
                    discussionEndButton.classList.toggle('d-none', !active);

                    // Accept -> diagnosis-prefill transition moment (§11.1,
                    // Milestone 3) — re-evaluated every time a session is
                    // loaded (start, respond, or reopening the panel on an
                    // already-accepted session), not just immediately after
                    // the accepting turn, so the call to action is never
                    // silently missed.
                    discussionAcceptedBanner.classList.toggle('d-none', data.session.status !== 'accepted');
                }

                function showDiscussionThinking(isThinking) {
                    discussionThinking.classList.toggle('d-none', !isThinking);
                }

                function showDiscussionError(message) {
                    discussionError.textContent = message;
                    discussionError.classList.remove('d-none');
                }

                function hideDiscussionError() {
                    discussionError.classList.add('d-none');
                }

                // "AI Discussion Unavailable" state (§11.5, Milestone 4) —
                // replaces the forms/end-button (nothing to submit or end
                // while no AI reviewer is reachable) but leaves any
                // existing transcript visible, since that's real prior
                // conversation, not part of the failure.
                function showDiscussionUnavailable(message) {
                    discussionOpenForm.classList.add('d-none');
                    discussionReplyForm.classList.add('d-none');
                    discussionEndButton.classList.add('d-none');
                    discussionAcceptedBanner.classList.add('d-none');
                    hideDiscussionError();
                    discussionUnavailableMessage.textContent = message;
                    discussionUnavailable.classList.remove('d-none');
                }

                function hideDiscussionUnavailable() {
                    discussionUnavailable.classList.add('d-none');
                }

                // Loads (or re-loads) the current discussion state via the
                // show endpoint — used both to open the panel and as the
                // "Try Again" action from the unavailable state (§11.5): a
                // student-triggered re-check, not an automatic retry loop.
                function loadDiscussionPanel() {
                    hideDiscussionError();
                    hideDiscussionUnavailable();
                    showDiscussionThinking(true);

                    fetch(discussionUrl, {
                        method: 'GET',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    })
                        .then((response) => (response.status === 404 ? { status: 'not_found' } : response.json()))
                        .then((data) => {
                            showDiscussionThinking(false);

                            if (data.status === 'not_found') {
                                discussionOpenForm.classList.remove('d-none');
                                return;
                            }

                            if (data.status === 'unavailable') {
                                showDiscussionUnavailable(data.message);
                                return;
                            }

                            applyDiscussionSession(data);
                        })
                        .catch(() => {
                            showDiscussionThinking(false);
                            showDiscussionError("Couldn't load Engineering Discussion — please try again.");
                        });
                }

                discussionStartButton.addEventListener('click', () => {
                    hideDiscussionError();
                    hideDiscussionUnavailable();
                    discussionTranscript.innerHTML = '';
                    discussionRoundCounter.textContent = '';
                    discussionOpenForm.classList.add('d-none');
                    discussionReplyForm.classList.add('d-none');
                    discussionEndButton.classList.add('d-none');
                    discussionAcceptedBanner.classList.add('d-none');
                    bootstrap.Modal.getOrCreateInstance(discussionModalEl).show();
                    loadDiscussionPanel();
                });

                document.getElementById('discussion-unavailable-retry')?.addEventListener('click', loadDiscussionPanel);

                discussionOpenForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const openingPosition = discussionOpenInput.value.trim();
                    if (!openingPosition) return;

                    hideDiscussionError();
                    discussionOpenSubmit.disabled = true;
                    showDiscussionThinking(true);

                    fetch(discussionUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ opening_position: openingPosition }),
                    })
                        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            showDiscussionThinking(false);
                            discussionOpenSubmit.disabled = false;

                            if (data && data.status === 'unavailable') {
                                showDiscussionUnavailable(data.message);
                                return;
                            }

                            if (!ok || data.status !== 'ok') {
                                showDiscussionError(data.message || "Couldn't start Engineering Discussion — please try again.");
                                return;
                            }

                            discussionOpenInput.value = '';
                            applyDiscussionSession(data);
                        })
                        .catch(() => {
                            showDiscussionThinking(false);
                            discussionOpenSubmit.disabled = false;
                            showDiscussionError("Couldn't start Engineering Discussion — please try again.");
                        });
                });

                discussionReplyForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const message = discussionReplyInput.value.trim();
                    if (!message) return;

                    hideDiscussionError();
                    discussionReplyInput.disabled = true;
                    discussionReplySubmit.disabled = true;
                    showDiscussionThinking(true);

                    fetch(discussionMessagesUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ message }),
                    })
                        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            showDiscussionThinking(false);

                            if (data && data.status === 'unavailable') {
                                discussionReplyInput.disabled = false;
                                discussionReplySubmit.disabled = false;
                                showDiscussionUnavailable(data.message);
                                return;
                            }

                            if (!ok || data.status !== 'ok') {
                                discussionReplyInput.disabled = false;
                                discussionReplySubmit.disabled = false;
                                showDiscussionError(data.message || "Couldn't send your message — please try again.");
                                return;
                            }

                            discussionReplyInput.value = '';
                            applyDiscussionSession(data);
                        })
                        .catch(() => {
                            showDiscussionThinking(false);
                            discussionReplyInput.disabled = false;
                            discussionReplySubmit.disabled = false;
                            showDiscussionError("Couldn't send your message — please try again.");
                        });
                });

                // End Discussion confirmation (§11.1, Milestone 3) —
                // mirrors #notebook-exit-confirm's pattern, but Cancel and
                // Close reopen the discussion panel modal explicitly
                // (rather than data-bs-dismiss) since this confirmation
                // opens from inside the already-open discussion panel.
                discussionEndButton.addEventListener('click', () => {
                    discussionEndConfirmError.classList.add('d-none');
                    bootstrap.Modal.getOrCreateInstance(discussionModalEl).hide();
                    bootstrap.Modal.getOrCreateInstance(discussionEndConfirmEl).show();
                });

                function reopenDiscussionPanel() {
                    bootstrap.Modal.getOrCreateInstance(discussionEndConfirmEl).hide();
                    bootstrap.Modal.getOrCreateInstance(discussionModalEl).show();
                }

                document.getElementById('discussion-end-confirm-cancel')?.addEventListener('click', reopenDiscussionPanel);
                document.getElementById('discussion-end-confirm-close')?.addEventListener('click', reopenDiscussionPanel);

                discussionEndConfirmProceed.addEventListener('click', function () {
                    const proceedButton = this;
                    proceedButton.disabled = true;
                    proceedButton.textContent = 'Ending…';
                    discussionEndConfirmError.classList.add('d-none');

                    fetch(discussionEndUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    })
                        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            if (!ok || data.status !== 'ok') {
                                discussionEndConfirmError.textContent = data.message || "Couldn't end the discussion — please try again.";
                                discussionEndConfirmError.classList.remove('d-none');
                                return;
                            }

                            applyDiscussionSession(data);
                            reopenDiscussionPanel();
                        })
                        .catch(() => {
                            discussionEndConfirmError.textContent = "Couldn't end the discussion — please try again.";
                            discussionEndConfirmError.classList.remove('d-none');
                        })
                        .finally(() => {
                            proceedButton.disabled = false;
                            proceedButton.textContent = 'End Discussion';
                        });
                });
            }
            @endif
        })();
    </script>
</x-workspace-layout>
