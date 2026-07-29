<x-admin-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Manual Reviews</h2>
    </x-slot>

    <div class="container-fluid py-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Incident</th>
                            <th>Student</th>
                            <th>Submitted</th>
                            <th># Pending Criteria</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($evaluations as $evaluation)
                            <tr>
                                <td>{{ $evaluation->caseAttempt->case->title }}</td>
                                <td>{{ $evaluation->caseAttempt->user->name }}</td>
                                <td>{{ $evaluation->evaluated_at->diffForHumans() }}</td>
                                <td>{{ $evaluation->metadata['pending_manual_review_count'] ?? 0 }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.evaluations.edit', $evaluation) }}" class="btn btn-sm btn-primary">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">Nothing is awaiting review right now.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
