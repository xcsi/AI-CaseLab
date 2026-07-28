<x-workspace-layout :title="$attempt->case->title" :exit-url="route('cases.show', $attempt->case)">
    {{--
        Milestone 1 — Workspace Shell: layout and navigation only. No
        evidence data, timer logic, autosave, or hint system yet — every
        pane below is a structural placeholder, filled in by later
        milestones.
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
                    <p class="text-secondary small mb-0">Evidence Explorer &mdash; coming in Milestone 2.</p>
                </x-workspace-pane>

                <x-workspace-pane title="Evidence Viewer" style="flex: 1 1 auto;">
                    <p class="text-secondary small mb-0">Evidence Viewer &mdash; coming in Milestone 2.</p>
                </x-workspace-pane>
            </div>

            <div id="ws-panel-notes" class="d-none d-lg-flex" style="flex: 0 0 320px;">
                <x-workspace-pane title="Engineering Notebook" class="flex-grow-1 border-end-0">
                    <p class="text-secondary small mb-0">Engineering Notebook &mdash; coming in Milestone 3.</p>
                </x-workspace-pane>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</x-workspace-layout>
