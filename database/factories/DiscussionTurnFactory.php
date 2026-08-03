<?php

namespace Database\Factories;

use App\Models\DiscussionSession;
use App\Models\DiscussionTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscussionTurn>
 */
class DiscussionTurnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discussion_session_id' => DiscussionSession::factory(),
            'sequence_order' => 1,
            'role' => 'student',
            'content' => $this->faker->sentence(),
        ];
    }
}
