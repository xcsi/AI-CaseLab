<?php

namespace Database\Factories;

use App\Models\CaseModel;
use App\Models\Hint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hint>
 */
class HintFactory extends Factory
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
            'order_index' => 0,
            'content' => fake()->sentence(),
            'score_penalty' => fake()->randomFloat(2, 1, 10),
        ];
    }
}
