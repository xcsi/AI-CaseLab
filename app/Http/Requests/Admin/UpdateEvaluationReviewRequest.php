<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('evaluation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criteria' => ['required', 'array'],
            'criteria.*.score' => ['required', 'numeric', 'min:0'],
            'criteria.*.comment' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Each criterion's score is capped by its own max_score — a dynamic,
     * per-row maximum that the static rules() array can't express, so
     * it's checked here against the evaluation's actual criterion results
     * rather than trusting a client-supplied ceiling.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $evaluation = $this->route('evaluation');
            $maxScores = $evaluation->criterionResults->pluck('max_score', 'id');

            foreach ((array) $this->input('criteria', []) as $resultId => $data) {
                $max = $maxScores->get((int) $resultId);

                if ($max === null) {
                    $validator->errors()->add("criteria.{$resultId}.score", 'This criterion does not belong to this evaluation.');

                    continue;
                }

                if (isset($data['score']) && (float) $data['score'] > (float) $max) {
                    $validator->errors()->add("criteria.{$resultId}.score", "Score cannot exceed {$max}.");
                }
            }
        });
    }
}
