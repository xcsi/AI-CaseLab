@php
    $renderer = match ($evidenceItem->evidenceType->code) {
        'log' => 'investigation.evidence.log',
        'code_snippet' => 'investigation.evidence.code-snippet',
        'db_snapshot' => 'investigation.evidence.db-snapshot',
        'api_response' => 'investigation.evidence.api-response',
        'screenshot' => 'investigation.evidence.screenshot',
        default => 'investigation.evidence.generic',
    };
@endphp

@include($renderer, ['evidenceItem' => $evidenceItem])
