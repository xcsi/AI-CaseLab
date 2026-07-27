<?php

namespace App\Http\Requests\Admin;

use App\Enums\MatchingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRubricCriterionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * A rubric criterion has no authorization independent of its case —
     * this is authoring the case's content, so it follows CasePolicy::update.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('case'));
    }

    /**
     * Assembles `expected_data` from the matching-type-specific raw form
     * field before validation, rather than accepting raw JSON from the
     * admin — evidence citations default to an empty set since evidence
     * authoring doesn't exist yet (Phase 7); `manual` needs no expected data.
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
     * Nesting a rule under `expected_data.keywords` would drop the whole
     * `expected_data` key from validated() when it's an empty array (Laravel
     * validator quirk: an empty array's dotted child rules suppress the
     * parent from the validated-data result) — so this validates the
     * assembled array directly instead of a nested key.
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
