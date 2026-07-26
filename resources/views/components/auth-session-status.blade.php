@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-success fw-medium small']) }}>
        {{ $status }}
    </div>
@endif
