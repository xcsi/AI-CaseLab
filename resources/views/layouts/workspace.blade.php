<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Investigation Workspace' }} — {{ config('app.name', 'AI CaseLab') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body class="workspace-body-root">
        <div class="d-flex flex-column vh-100">
            {{--
                The global Engineering Office nav is intentionally absent here —
                the Workspace collapses to a slim bar so the investigation gets
                the whole screen, per the approved UX spec.
            --}}
            <header class="workspace-topbar d-flex flex-wrap flex-sm-nowrap align-items-center justify-content-between gap-2 gap-sm-3 px-3 py-2 border-bottom bg-white flex-shrink-0">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <a href="{{ $exitUrl }}" id="workspace-exit-link" class="text-secondary text-decoration-none text-nowrap" title="Exit Investigation">
                        &larr; Exit
                    </a>
                    <span class="fw-semibold text-truncate min-w-0">{{ $title }}</span>
                </div>

                <div class="d-flex align-items-center gap-2 gap-sm-3 flex-shrink-0">
                    <span class="text-secondary small font-monospace" id="workspace-timer" data-started-at="{{ $startedAt }}">
                        ⏱ --:--:--
                    </span>
                    <span class="text-secondary small text-nowrap" id="workspace-progress" data-viewed="{{ $evidenceViewedCount }}" data-total="{{ $evidenceTotalCount }}">
                        {{ $evidenceViewedCount }}/{{ $evidenceTotalCount }} viewed
                    </span>
                    <button type="button" class="btn btn-primary btn-sm" disabled title="Coming in a later milestone">
                        Submit Diagnosis
                    </button>
                </div>
            </header>

            <main class="flex-grow-1 min-h-0" style="overflow: hidden;">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
