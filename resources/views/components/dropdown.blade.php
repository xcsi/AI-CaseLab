@props(['align' => 'right', 'contentClasses' => 'py-1'])

@php
$alignmentClass = $align === 'left' ? '' : 'dropdown-menu-end';
@endphp

<div class="dropdown">
    <div role="button" data-bs-toggle="dropdown" aria-expanded="false">
        {{ $trigger }}
    </div>

    <ul class="dropdown-menu {{ $alignmentClass }} {{ $contentClasses }}">
        {{ $content }}
    </ul>
</div>
