@php
    $layout = $layout ?? 'app';
@endphp

<x-dynamic-component :component="$layout . '-layout'">
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ $title }}</h2>
    </x-slot>

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body text-center text-secondary py-5">
                <p class="mb-0">{{ $title }} is coming in a later phase of the build.</p>
            </div>
        </div>
    </div>
</x-dynamic-component>
