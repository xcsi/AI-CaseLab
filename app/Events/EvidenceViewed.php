<?php

namespace App\Events;

use App\Models\CaseAttempt;
use App\Models\EvidenceItem;

class EvidenceViewed
{
    public function __construct(
        public readonly CaseAttempt $attempt,
        public readonly EvidenceItem $evidenceItem,
    ) {}
}
