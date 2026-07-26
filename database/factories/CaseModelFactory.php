<?php

namespace Database\Factories;

use App\Enums\CaseDifficulty;
use App\Enums\CaseStatus;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseModel>
 */
class CaseModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'category_id' => Category::factory(),
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => str($title)->slug(),
            'summary' => fake()->sentence(),
            'ticket_content' => fake()->paragraphs(3, true),
            'learning_outcomes' => fake()->sentence(),
            'difficulty' => fake()->randomElement(CaseDifficulty::cases())->value,
            'estimated_minutes' => fake()->numberBetween(15, 60),
            'status' => CaseStatus::Draft->value,
            'version' => 1,
            'model_solution_summary' => fake()->paragraph(),
            'max_score' => 0,
            'allow_reattempt' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CaseStatus::Published->value,
        ]);
    }
}
