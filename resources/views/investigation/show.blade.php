@php
    $case = $attempt->case;
    $groupedEvidence = $case->evidenceItems->groupBy(fn ($item) => $item->evidenceType->label);
    $evidenceTotalCount = $case->evidenceItems->count();
    $evidenceViewedCount = $viewedEvidenceItemIds->count();
@endphp

<x-workspace-layout
    :title="$case->title"
    :exit-url="route('cases.show', $case)"
    :evidence-viewed-count="$evidenceViewedCount"
    :evidence-total-count="$evidenceTotalCount"
>
    {{--
        Milestone 2 — Evidence Explorer + Viewer. Still no Engineering
        Notebook, autosave, hint unlocking, timer logic, or diagnosis
        submission — those are later milestones.
    --}}

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
                <x-workspace-pane title="Engineering Notebook" class="flex-grow-1 border-end-0">
                    <p class="text-secondary small mb-0">Engineering Notebook &mdash; coming in Milestone 3.</p>
                </x-workspace-pane>
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
