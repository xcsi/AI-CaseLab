@php
    $path = $evidenceItem->payload['path'] ?? null;
    $caption = $evidenceItem->payload['caption'] ?? null;
    $url = $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
@endphp

<div class="evidence-screenshot-viewer">
    @if ($url)
        <div class="evidence-screenshot-frame">
            <img
                src="{{ $url }}"
                alt="{{ $caption ?? $evidenceItem->title }}"
                class="evidence-screenshot-image evidence-screenshot-loading"
                data-lightbox-trigger
                onload="this.classList.remove('evidence-screenshot-loading')"
                onerror="this.classList.remove('evidence-screenshot-loading'); this.classList.add('evidence-screenshot-error');"
            >
        </div>
        @if ($caption)
            <p class="text-secondary small mt-2 mb-0">{{ $caption }}</p>
        @endif
    @else
        <p class="text-secondary small mb-0">No screenshot has been attached to this evidence item.</p>
    @endif
</div>
