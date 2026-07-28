<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CaseAttempt;
use App\Models\EvidenceItem;
use App\Services\EvidenceInvestigationService;
use Illuminate\Http\JsonResponse;

class EvidenceController extends Controller
{
    public function __construct(
        private readonly EvidenceInvestigationService $evidenceInvestigation,
    ) {}

    public function recordView(CaseAttempt $attempt, EvidenceItem $evidenceItem): JsonResponse
    {
        abort_unless($evidenceItem->case_id === $attempt->case_id, 404);

        $this->evidenceInvestigation->recordView($attempt, $evidenceItem);

        return response()->json(['status' => 'ok']);
    }
}
