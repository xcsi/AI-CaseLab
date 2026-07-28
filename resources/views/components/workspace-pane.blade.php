@props(['title', 'bodyClass' => 'p-3'])

<section {{ $attributes->merge(['class' => 'workspace-pane d-flex flex-column border-end bg-white min-h-0']) }}>
    <div class="workspace-pane-header px-3 py-2 border-bottom small fw-semibold text-uppercase text-secondary flex-shrink-0">
        {{ $title }}
    </div>
    <div class="workspace-pane-body flex-grow-1 overflow-auto min-h-0 {{ $bodyClass }}">
        {{ $slot }}
    </div>
</section>
