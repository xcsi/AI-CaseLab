<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Models\CaseAttempt;
use App\Models\Diagnosis;
use Illuminate\Support\Facades\DB;

class DiagnosisSubmissionService
{
    /**
     * Idempotent: an attempt can only ever have one diagnosis
     * (case_attempts.id is unique on diagnoses), so a stale resubmission —
     * e.g. a back-button double-post — returns the existing diagnosis
     * instead of erroring.
     */
    public function submit(CaseAttempt $attempt, array $data): Diagnosis
    {
        return DB::transaction(function () use ($attempt, $data) {
            $existing = $attempt->diagnosis()->first();

            if ($existing) {
                return $existing;
            }

            $submittedAt = now();

            $diagnosis = $attempt->diagnosis()->create([
                'root_cause_text' => $data['root_cause_text'],
                'proposed_fix_text' => $data['proposed_fix_text'],
                'confidence_level' => $data['confidence_level'],
                'submitted_at' => $submittedAt,
            ]);

            $diagnosis->citedEvidence()->sync($data['cited_evidence_ids'] ?? []);

            $attempt->update([
                'status' => AttemptStatus::Submitted,
                'submitted_at' => $submittedAt,
            ]);

            return $diagnosis;
        });
    }
}
