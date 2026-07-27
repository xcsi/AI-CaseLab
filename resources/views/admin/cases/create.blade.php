<x-admin-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">Add Case</h2>
    </x-slot>

    <div class="container-fluid py-4">
        <form method="POST" action="{{ route('admin.cases.store') }}" id="case-form">
            @csrf
            @include('admin.cases._form', ['case' => null, 'categories' => $categories, 'difficulties' => $difficulties])
        </form>
    </div>

    @include('admin.cases._slug-script', ['titleId' => 'case-title', 'slugId' => 'case-slug'])
</x-admin-layout>
