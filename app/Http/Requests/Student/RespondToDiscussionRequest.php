<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class RespondToDiscussionRequest extends FormRequest
{
    /**
     * Ownership is already enforced by the attempt.owner route middleware,
     * matching StoreDiagnosisRequest's exact convention.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
