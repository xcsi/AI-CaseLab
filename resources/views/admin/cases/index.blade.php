<x-admin-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fs-4 fw-semibold mb-0">Cases</h2>
            @can('create', App\Models\CaseModel::class)
                <a href="{{ route('admin.cases.create') }}" class="btn btn-primary">Add Case</a>
            @endcan
        </div>
    </x-slot>

    <div class="container-fluid py-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Difficulty</th>
                            <th>Status</th>
                            <th>Est. Minutes</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cases as $case)
                            <tr>
                                <td>{{ $case->title }}</td>
                                <td>{{ $case->category->name }}</td>
                                <td>
                                    <span @class([
                                        'badge',
                                        'text-bg-success' => $case->difficulty === App\Enums\CaseDifficulty::Easy,
                                        'text-bg-warning' => $case->difficulty === App\Enums\CaseDifficulty::Medium,
                                        'text-bg-danger' => $case->difficulty === App\Enums\CaseDifficulty::Hard,
                                    ])>{{ $case->difficulty->value }}</span>
                                </td>
                                <td>
                                    <span @class([
                                        'badge',
                                        'text-bg-secondary' => $case->status === App\Enums\CaseStatus::Draft,
                                        'text-bg-success' => $case->status === App\Enums\CaseStatus::Published,
                                        'text-bg-dark' => $case->status === App\Enums\CaseStatus::Archived,
                                    ])>{{ $case->status->value }}</span>
                                </td>
                                <td>{{ $case->estimated_minutes }}</td>
                                <td>{{ $case->updated_at->diffForHumans() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.cases.edit', $case) }}" class="btn btn-sm btn-outline-secondary">
                                        @can('update', $case) Edit @else View @endcan
                                    </a>
                                    @can('delete', $case)
                                        <form method="POST" action="{{ route('admin.cases.destroy', $case) }}" class="d-inline"
                                            onsubmit="return confirm('Archive {{ $case->title }}? This can be undone by restoring from the database.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No cases yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
