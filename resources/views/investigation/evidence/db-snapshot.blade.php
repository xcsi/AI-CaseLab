@php
    $table = $evidenceItem->payload['table'] ?? null;
    $columns = $evidenceItem->payload['columns'] ?? [];
    $rows = $evidenceItem->payload['rows'] ?? [];
    $columnNames = collect($columns)->map(fn ($column) => is_array($column) ? ($column['name'] ?? '') : $column);
    $columnTypes = collect($columns)->filter(fn ($column) => is_array($column) && ! empty($column['type']));
@endphp

<div class="evidence-db-viewer">
    @if ($table)
        <div class="small text-secondary mb-2">Table: <code>{{ $table }}</code></div>
    @endif

    @if ($columnNames->isEmpty() || empty($rows))
        <p class="text-secondary small mb-0">No data captured for this evidence item.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        @foreach ($columnNames as $name)
                            <th>{{ $name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columnNames as $name)
                                <td>{{ $row[$name] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($columnTypes->isNotEmpty())
            <div class="mt-3">
                <button type="button" class="btn btn-sm btn-link p-0 evidence-toggle" data-target="evidence-db-columns-{{ $evidenceItem->id }}">
                    Show column types
                </button>
                <div id="evidence-db-columns-{{ $evidenceItem->id }}" class="d-none small mt-2">
                    <ul class="mb-0">
                        @foreach ($columnTypes as $column)
                            <li><code>{{ $column['name'] }}</code> — {{ $column['type'] }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    @endif
</div>
