@php
    $filename = $evidenceItem->payload['filename'] ?? null;
    $language = $evidenceItem->payload['language'] ?? null;
    $code = $evidenceItem->payload['code'] ?? '';
    $highlightLines = $evidenceItem->payload['highlight_lines'] ?? [];
@endphp

<div class="evidence-code-viewer">
    @if ($filename || $language)
        <div class="evidence-code-header">
            @if ($filename)
                <span class="evidence-code-filename">{{ $filename }}</span>
            @endif
            @if ($language)
                <span class="badge text-bg-secondary">{{ $language }}</span>
            @endif
        </div>
    @endif

    @if ($code === '')
        <p class="text-secondary small mb-0">No code content for this evidence item.</p>
    @else
        <pre class="evidence-code-body mb-0"><code
            >@foreach (explode("\n", $code) as $index => $codeLine)<span class="evidence-code-line{{ in_array($index + 1, $highlightLines) ? ' evidence-code-line-highlight' : '' }}">{{ $codeLine }}</span>
@endforeach</code></pre>
    @endif
</div>
