@php
    $payload = $evidenceItem->payload ?? [];
    $status = $payload['status'] ?? null;
    $statusBadge = match (true) {
        $status === null => 'text-bg-secondary',
        $status < 300 => 'text-bg-success',
        $status < 400 => 'text-bg-primary',
        $status < 500 => 'text-bg-warning',
        default => 'text-bg-danger',
    };
    $hasHeaders = ! empty($payload['request_headers']) || ! empty($payload['response_headers']);
@endphp

<div class="evidence-api-viewer">
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="badge text-bg-dark">{{ strtoupper($payload['method'] ?? 'GET') }}</span>
        <code class="small">{{ $payload['endpoint'] ?? '—' }}</code>
        @if ($status !== null)
            <span class="badge {{ $statusBadge }} ms-auto">{{ $status }}</span>
        @endif
    </div>

    <div class="mb-3">
        <div class="small fw-semibold text-secondary text-uppercase mb-1">Response Body</div>
        @if (empty($payload['response_body']))
            <p class="text-secondary small mb-0">No response body captured.</p>
        @else
            <pre class="evidence-json-body mb-0">{{ json_encode($payload['response_body'], JSON_PRETTY_PRINT) }}</pre>
        @endif
    </div>

    @if (! empty($payload['request_body']))
        <div class="mb-3">
            <div class="small fw-semibold text-secondary text-uppercase mb-1">Request Body</div>
            <pre class="evidence-json-body mb-0">{{ json_encode($payload['request_body'], JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif

    @if ($hasHeaders)
        <button type="button" class="btn btn-sm btn-link p-0 evidence-toggle" data-target="evidence-api-headers-{{ $evidenceItem->id }}">
            Show headers
        </button>
        <div id="evidence-api-headers-{{ $evidenceItem->id }}" class="d-none small mt-2">
            @if (! empty($payload['request_headers']))
                <div class="fw-semibold text-secondary text-uppercase mb-1">Request Headers</div>
                <pre class="evidence-json-body mb-2">{{ json_encode($payload['request_headers'], JSON_PRETTY_PRINT) }}</pre>
            @endif
            @if (! empty($payload['response_headers']))
                <div class="fw-semibold text-secondary text-uppercase mb-1">Response Headers</div>
                <pre class="evidence-json-body mb-0">{{ json_encode($payload['response_headers'], JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
    @endif
</div>
