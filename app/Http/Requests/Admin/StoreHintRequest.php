<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * A hint has no authorization independent of its case — this is
     * authoring the case's content, so it follows CasePolicy::update.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('case'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'score_penalty' => ['required', 'numeric', 'min:0'],
        ];
    }
}
