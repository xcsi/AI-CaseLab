<?php

namespace Database\Factories;

use App\Enums\ConfidenceLevel;
use App\Models\CaseAttempt;
use App\Models\Diagnosis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_attempt_id' => CaseAttempt::factory(),
            'root_cause_text' => fake()->paragraph(),
            'proposed_fix_text' => fake()->paragraph(),
            'confidence_level' => ConfidenceLevel::Medium->value,
            'submitted_at' => now(),
        ];
    }
}
