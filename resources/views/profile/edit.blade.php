<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="container py-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
