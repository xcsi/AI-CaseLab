<?php

namespace App\Listeners;

use App\Events\EvidenceViewed;
use App\Models\EvidenceView;

class RecordEvidenceView
{
    /**
     * Persists/increments the evidence_views row for this attempt +
     * evidence item — one row per pair (Database Design: unique on
     * case_attempt_id/evidence_item_id), view_count incrementing on every
     * open per the approved UX spec.
     */
    public function handle(EvidenceViewed $event): void
    {
        $view = EvidenceView::firstOrNew([
            'case_attempt_id' => $event->attempt->id,
            'evidence_item_id' => $event->evidenceItem->id,
        ]);

        $view->view_count = ($view->view_count ?? 0) + 1;
        $view->first_viewed_at ??= now();
        $view->last_viewed_at = now();
        $view->save();
    }
}
