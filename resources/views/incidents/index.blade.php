@php
    $selectedDifficulties = collect(request('difficulty', []));
    $isAuthed = auth()->check();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Assigned Incidents</h2>
    </x-slot>

    <div class="container py-4">
        <div class="d-md-none mb-3">
            <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="offcanvas" data-bs-target="#incident-filters">
                Filters
            </button>
        </div>

        <form method="GET" action="{{ route('cases.index') }}" id="incident-filters-form">
            <div class="offcanvas-bottom offcanvas-md border-0" tabindex="-1" id="incident-filters" aria-labelledby="incident-filters-label">
                <div class="offcanvas-header d-md-none">
                    <h5 class="offcanvas-title" id="incident-filters-label">Filters</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-md-flex align-items-center flex-wrap gap-3 bg-white border rounded-3 p-3 mb-4">
                    <select name="category" class="form-select form-select-sm" style="max-width: 180px;">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>

                    <div class="btn-group" role="group" aria-label="Difficulty">
                        @foreach (App\Enums\CaseDifficulty::cases() as $difficulty)
                            <input type="checkbox" class="btn-check" name="difficulty[]" value="{{ $difficulty->value }}"
                                id="difficulty-{{ $difficulty->value }}" autocomplete="off"
                                @checked($selectedDifficulties->contains($difficulty->value))>
                            <label class="btn btn-sm btn-outline-secondary" for="difficulty-{{ $difficulty->value }}">
                                {{ ucfirst($difficulty->value) }}
                            </label>
                        @endforeach
                    </div>

                    @if ($isAuthed)
                        <select name="status" class="form-select form-select-sm" style="max-width: 170px;">
                            <option value="">All Statuses</option>
                            <option value="not_started" @selected(request('status') === 'not_started')>Not Started</option>
                            <option value="in_progress" @selected(request('status') === 'in_progress')>In Progress</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </select>
                    @endif

                    <select name="sort" class="form-select form-select-sm" style="max-width: 170px;">
                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option>
                        <option value="difficulty" @selected(request('sort') === 'difficulty')>Difficulty</option>
                        <option value="title" @selected(request('sort') === 'title')>Title A&ndash;Z</option>
                    </select>

                    <div class="ms-md-auto" style="min-width: 220px;">
                        <input type="search" name="search" id="incident-search" class="form-control form-control-sm"
                            placeholder="Search incidents&hellip;" value="{{ request('search') }}">
                    </div>

                    @if (request()->anyFilled(['category', 'difficulty', 'status', 'search']) || request('sort', 'newest') !== 'newest')
                        <a href="{{ route('cases.index') }}" class="btn btn-sm btn-link text-decoration-none" data-incident-filter-link>Clear Filters</a>
                    @endif
                </div>
            </div>
        </form>

        <div id="incident-status" class="visually-hidden" role="status" aria-live="polite"></div>

        <div id="incident-results">
            @include('incidents._results')
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('incident-filters-form');
            const resultsEl = document.getElementById('incident-results');
            const statusEl = document.getElementById('incident-status');
            const searchInput = document.getElementById('incident-search');
            let searchTimeout;
            let activeRequest = null;

            function syncFormFromUrl(url) {
                const params = new URL(url, window.location.origin).searchParams;

                if (form.elements.category) {
                    form.elements.category.value = params.get('category') || '';
                }
                if (form.elements.status) {
                    form.elements.status.value = params.get('status') || '';
                }
                if (form.elements.sort) {
                    form.elements.sort.value = params.get('sort') || 'newest';
                }
                if (searchInput) {
                    searchInput.value = params.get('search') || '';
                }

                const selectedDifficulties = params.getAll('difficulty[]');
                form.querySelectorAll('input[name="difficulty[]"]').forEach((checkbox) => {
                    checkbox.checked = selectedDifficulties.includes(checkbox.value);
                });
            }

            function fetchResults(url, { pushState = true } = {}) {
                if (activeRequest) {
                    activeRequest.abort();
                }
                const controller = new AbortController();
                activeRequest = controller;

                resultsEl.setAttribute('aria-busy', 'true');
                resultsEl.classList.add('incident-results-loading');

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.text();
                    })
                    .then((html) => {
                        resultsEl.innerHTML = html;

                        const statusText = resultsEl.querySelector('[data-incident-status]');
                        if (statusText) {
                            statusEl.textContent = statusText.textContent.trim();
                        }

                        if (pushState) {
                            window.history.pushState({}, '', url);
                        }
                        syncFormFromUrl(url);
                    })
                    .catch((error) => {
                        if (error.name === 'AbortError') {
                            return;
                        }
                        // Fall back to a real navigation if the fetch itself failed
                        // (network error, JS disabled path never reaches here).
                        window.location.href = url;
                    })
                    .finally(() => {
                        if (activeRequest === controller) {
                            resultsEl.removeAttribute('aria-busy');
                            resultsEl.classList.remove('incident-results-loading');
                            activeRequest = null;
                        }
                    });
            }

            function currentFormUrl() {
                const params = new URLSearchParams(new FormData(form));
                const query = params.toString();
                return form.action + (query ? '?' + query : '');
            }

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                fetchResults(currentFormUrl());
            });

            form.addEventListener('change', (event) => {
                if (event.target === searchInput) {
                    return;
                }
                fetchResults(currentFormUrl());
            });

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => fetchResults(currentFormUrl()), 450);
                });
            }

            document.addEventListener('click', (event) => {
                const link = event.target.closest('.pagination .page-link, [data-incident-filter-link]');
                if (!link || !link.href) {
                    return;
                }
                event.preventDefault();
                fetchResults(link.href);
            });

            window.addEventListener('popstate', () => {
                fetchResults(window.location.href, { pushState: false });
            });
        });
    </script>
</x-app-layout>
