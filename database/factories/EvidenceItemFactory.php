<?php

namespace Database\Factories;

use App\Models\CaseModel;
use App\Models\EvidenceItem;
use App\Models\EvidenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceItem>
 */
class EvidenceItemFactory extends Factory
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
            'evidence_type_id' => EvidenceType::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'sequence_order' => 0,
            'payload' => ['text' => fake()->paragraph()],
        ];
    }
}
