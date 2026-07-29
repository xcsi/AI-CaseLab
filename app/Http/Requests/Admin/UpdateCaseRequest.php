<?php

namespace App\Http\Requests\Admin;

use App\Enums\CaseDifficulty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('case'));
    }

    /**
     * Checkboxes omit the field entirely when unchecked, so normalize it
     * here rather than making the column non-nullable-but-sometimes-absent.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['allow_reattempt' => $this->boolean('allow_reattempt')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('cases', 'slug')->ignore($this->route('case'))->whereNull('deleted_at'),
            ],
            'summary' => ['nullable', 'string'],
            'ticket_content' => ['required', 'string'],
            'learning_outcomes' => ['nullable', 'string'],
            'difficulty' => ['required', new Enum(CaseDifficulty::class)],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'model_solution_summary' => ['nullable', 'string'],
            'allow_reattempt' => ['boolean'],
        ];
    }
}
