{{--
    Fallback for evidence types the approved UX spec doesn't define a
    dedicated renderer for (e.g. configuration, deployment_history) — shows
    the raw payload rather than silently hiding it.
--}}
<div class="evidence-generic-viewer">
    @if (empty($evidenceItem->payload))
        <p class="text-secondary small mb-0">No content captured for this evidence item.</p>
    @else
        <pre class="evidence-json-body mb-0">{{ json_encode($evidenceItem->payload, JSON_PRETTY_PRINT) }}</pre>
    @endif
</div>
