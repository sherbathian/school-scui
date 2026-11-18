<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake()->unique()->word(),
            'status' => 'active',
        ];
    }
}
