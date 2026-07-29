<?php

namespace App\Services;

use App\Events\EvidenceViewed;
use App\Models\CaseAttempt;
use App\Models\EvidenceItem;

class EvidenceInvestigationService
{
    /**
     * Records that the student opened this evidence item during this
     * attempt. The actual persistence lives in RecordEvidenceView (fired
     * as a side effect, not written here directly) — matches the
     * architecture doc's Example 1 trace: the service's job is "this was
     * viewed," not "know how view analytics are stored."
     */
    public function recordView(CaseAttempt $attempt, EvidenceItem $evidenceItem): void
    {
        event(new EvidenceViewed($attempt, $evidenceItem));
    }
}
