<?php

namespace App\Http\Requests\Admin;

use App\Enums\CaseDifficulty;
use App\Models\CaseModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', CaseModel::class);
    }

    /**
     * Checkboxes omit the field entirely when unchecked, so normalize it
     * here rather than making the column non-nullable-but-sometimes-absent.
     * The discussion fields get the same "empty means absent" treatment:
     * a blank select/number input arrives as "", not omitted, which would
     * otherwise fail `nullable` + `Rule::in`/`integer` outright.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_reattempt' => $this->boolean('allow_reattempt'),
            'discussion_enabled' => $this->boolean('discussion_enabled'),
            'discussion_default_persona' => $this->filled('discussion_default_persona')
                ? $this->input('discussion_default_persona')
                : null,
            'discussion_max_rounds' => $this->filled('discussion_max_rounds')
                ? $this->input('discussion_max_rounds')
                : null,
        ]);
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
                Rule::unique('cases', 'slug')->whereNull('deleted_at'),
            ],
            'summary' => ['nullable', 'string'],
            'ticket_content' => ['required', 'string'],
            'learning_outcomes' => ['nullable', 'string'],
            'difficulty' => ['required', new Enum(CaseDifficulty::class)],
            'estimated_minutes' => ['required', 'integer', 'min:1'],
            'model_solution_summary' => ['nullable', 'string'],
            'allow_reattempt' => ['boolean'],
            'discussion_enabled' => ['boolean'],
            'discussion_default_persona' => [
                'nullable', 'string',
                Rule::in(array_keys(config('discussion_personas'))),
                Rule::requiredIf(fn () => $this->boolean('discussion_enabled')),
            ],
            'discussion_max_rounds' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
