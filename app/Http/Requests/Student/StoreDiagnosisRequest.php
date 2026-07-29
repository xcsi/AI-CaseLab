<?php

namespace App\Http\Requests\Student;

use App\Enums\ConfidenceLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiagnosisRequest extends FormRequest
{
    /**
     * Ownership is already enforced by the attempt.owner route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $caseId = $this->route('attempt')->case_id;

        return [
            'root_cause_text' => ['required', 'string', 'max:5000'],
            'proposed_fix_text' => ['required', 'string', 'max:5000'],
            'confidence_level' => ['required', Rule::enum(ConfidenceLevel::class)],
            'cited_evidence_ids' => ['array'],
            'cited_evidence_ids.*' => [
                'integer',
                Rule::exists('evidence_items', 'id')->where('case_id', $caseId),
            ],
        ];
    }
}
