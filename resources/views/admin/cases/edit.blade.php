@php
    $readOnly = auth()->user()->cannot('update', $case);
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="fs-4 fw-semibold mb-0">{{ $readOnly ? 'View Case' : 'Edit Case' }}: {{ $case->title }}</h2>
    </x-slot>

    <div class="container-fluid py-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.cases.update', $case) }}" id="case-form">
            @csrf
            @method('PUT')
            @include('admin.cases._form', ['case' => $case, 'categories' => $categories, 'difficulties' => $difficulties, 'readOnly' => $readOnly])
        </form>

        @include('admin.cases._hints', ['case' => $case, 'readOnly' => $readOnly])
    </div>

    @unless ($readOnly)
        @include('admin.cases._slug-script', ['titleId' => 'case-title', 'slugId' => 'case-slug'])
    @endunless
</x-admin-layout>
