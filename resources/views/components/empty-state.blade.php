@props([
    'message',
    'actionUrl' => null,
    'actionLabel' => null,
    'compact' => false,
    'actionClass' => 'btn-ghost',
])

{{--
    Design System v1 empty state: an outline icon + one factual sentence +
    an optional single Ghost-button action, centered in the panel it
    belongs to. No illustrations, no mascots. `compact` shrinks the icon
    and spacing for narrow contexts (e.g. a sidebar list), rather than
    forcing the same full-size treatment everywhere regardless of width.
--}}
<div {{ $attributes->class(['empty-state', 'empty-state-compact' => $compact]) }}>
    <svg class="empty-state-icon" width="{{ $compact ? 20 : 28 }}" height="{{ $compact ? 20 : 28 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 12h4l2 3h4l2-3h4" />
        <path d="M5.5 5h13l1.5 7v7a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7l1.5-7z" />
    </svg>
    <p class="empty-state-message">{{ $message }}</p>
    @if ($actionUrl)
        <a href="{{ $actionUrl }}" class="btn {{ $actionClass }} btn-sm">{{ $actionLabel }}</a>
    @endif
</div>
