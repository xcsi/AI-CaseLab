<?php

namespace Database\Factories;

use App\Enums\MatchingType;
use App\Models\CaseModel;
use App\Models\RubricCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricCriterion>
 */
class RubricCriterionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => CaseModel::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'weight' => fake()->randomFloat(2, 5, 25),
            'matching_type' => MatchingType::Keyword->value,
            'expected_data' => ['keywords' => fake()->words(3)],
        ];
    }
}
