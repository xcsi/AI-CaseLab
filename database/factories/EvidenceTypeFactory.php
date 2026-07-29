<?php

namespace Database\Factories;

use App\Models\EvidenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceType>
 */
class EvidenceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->word();

        return [
            'code' => $code,
            'label' => ucfirst($code),
        ];
    }
}
