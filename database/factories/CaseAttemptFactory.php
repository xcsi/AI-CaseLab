<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseAttempt>
 */
class CaseAttemptFactory extends Factory
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
            'user_id' => User::factory(),
            'status' => AttemptStatus::InProgress->value,
            'case_version' => 1,
            'started_at' => now(),
            'max_possible_score' => 100,
        ];
    }
}
