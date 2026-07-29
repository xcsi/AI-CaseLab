<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRubricCriterionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('rubric_criterion')->case);
    }

    /**
     * @see StoreRubricCriterionRequest::prepareForValidation()
     */
    protected function prepareForValidation(): void
    {
        $expectedData = match ($this->input('matching_type')) {
            MatchingType::Keyword->value => [
                'keywords' => collect(preg_split('/[\r\n,]+/', (string) $this->input('keywords', '')))
                    ->map(fn ($keyword) => trim($keyword))
                    ->filter()
                    ->values()
                    ->all(),
            ],
            MatchingType::EvidenceCitation->value => [
                'required_evidence_ids' => [],
            ],
            default => [],
        };

        $this->merge(['expected_data' => $expectedData]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'weight' => ['required', 'numeric', 'min:0.01'],
            'matching_type' => ['required', new Enum(MatchingType::class)],
            'expected_data' => ['array', $this->atLeastOneKeywordWhenKeywordType()],
        ];
    }

    /**
     * @see StoreRubricCriterionRequest::atLeastOneKeywordWhenKeywordType()
     */
    private function atLeastOneKeywordWhenKeywordType(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($this->input('matching_type') === MatchingType::Keyword->value && empty($value['keywords'])) {
                $fail('At least one keyword is required for the keyword matching type.');
            }
        };
    }
}
