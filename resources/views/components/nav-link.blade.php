@props(['active' => false])

<a {{ $attributes->merge(['class' => 'nav-link' . ($active ? ' active' : '')]) }}>
    {{ $slot }}
</a>
