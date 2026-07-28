@php
    $lines = $evidenceItem->payload['lines'] ?? [];
@endphp

<div class="evidence-log-viewer">
    @if (empty($lines))
        <p class="text-secondary small mb-0">No log content for this evidence item.</p>
    @else
        <input type="text" class="form-control form-control-sm mb-2 evidence-log-filter"
            placeholder="Filter log lines…" data-target="evidence-log-body-{{ $evidenceItem->id }}">

        <div class="evidence-log-body" id="evidence-log-body-{{ $evidenceItem->id }}">
            @foreach ($lines as $line)
                @php $level = strtolower($line['level'] ?? 'info'); @endphp
                <div class="evidence-log-line evidence-log-level-{{ $level }}">
                    @if (! empty($line['timestamp']))
                        <span class="evidence-log-timestamp">{{ $line['timestamp'] }}</span>
                    @endif
                    <span class="evidence-log-level-tag">{{ strtoupper($level) }}</span>
                    <span class="evidence-log-text">{{ $line['text'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
