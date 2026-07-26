<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                {{ __("You're logged in!") }}
            </div>
        </div>
    </div>
</x-app-layout>
