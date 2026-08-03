@php
    $layout = $layout ?? 'app';
@endphp

<x-dynamic-component :component="$layout . '-layout'">
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ $title }}</h2>
    </x-slot>

    <div class="container py-4">
        <div class="card">
            <div class="card-body">
                <x-empty-state :message="$title . ' is coming in a later phase of the build.'" />
            </div>
        </div>
    </div>
</x-dynamic-component>
