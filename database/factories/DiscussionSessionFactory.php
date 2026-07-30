<?php

namespace Database\Factories;

use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionSession>
 */
class DiscussionSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => CaseAttempt::factory(),
            'persona' => 'mentor',
            'status' => 'active',
            'round_count' => 0,
            'max_rounds' => 6,
            'started_at' => now(),
        ];
    }
}
