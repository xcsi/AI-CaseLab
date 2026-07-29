<x-app-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Incident Briefing</h2>
    </x-slot>

    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="mb-3">This incident is no longer available.</p>
                <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary btn-sm">Back to Assigned Incidents</a>
            </div>
        </div>
    </div>
</x-app-layout>
