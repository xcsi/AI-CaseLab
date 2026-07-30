<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartDiscussionRequest extends FormRequest
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
            'opening_position' => ['required', 'string', 'max:5000'],
            // Validated against the live config, not a hardcoded list, so a
            // persona added later doesn't need this request updated too.
            'persona' => ['nullable', 'string', Rule::in(array_keys(config('discussion_personas')))],
        ];
    }
}
