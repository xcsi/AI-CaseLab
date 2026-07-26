@props(['name', 'show' => false, 'maxWidth' => 'lg'])

@php
$maxWidthClass = match ($maxWidth) {
    'sm' => 'modal-sm',
    'xl' => 'modal-xl',
    default => 'modal-lg',
};
@endphp

<div class="modal fade" id="{{ $name }}" tabindex="-1" aria-hidden="true" data-show="{{ $show ? '1' : '0' }}">
    <div class="modal-dialog {{ $maxWidthClass }} modal-dialog-centered">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
