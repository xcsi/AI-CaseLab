{{--
    The support ticket is rendered from the case's own ticket_content, not
    an evidence_items row — per Database Design Decision 1's note that
    support_ticket evidence is "rare, usually inline."
--}}
<div class="evidence-ticket-viewer">
    <p class="mb-0" style="white-space: pre-line;">{{ $case->ticket_content }}</p>
</div>
