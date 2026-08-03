@props(['name', 'size' => 16])

{{--
    Design System v1 icon system (Milestone 6): a single, consistent
    outline-stroke icon set — 24px grid, 1.5px stroke, round caps/joins,
    `stroke="currentColor"` so every icon inherits whatever text color its
    context already sets (e.g. Bootstrap's .text-success/.text-danger
    utilities work on these exactly like they do on text). Never filled,
    never colored on its own, never an emoji — a fixed, hand-authored set
    matching the exact glyphs approved in the Design System spec's icon
    row, not a third-party library dependency.
--}}
@php
    $paths = [
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'dot' => '<circle cx="12" cy="12" r="3"/>',
        'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
        'arrow-up' => '<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>',
        'arrow-down' => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
    ];
@endphp
<svg {{ $attributes->merge([
        'width' => $size,
        'height' => $size,
        'viewBox' => '0 0 24 24',
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.5',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
    ]) }}>{!! $paths[$name] ?? '' !!}</svg>
