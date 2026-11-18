<?php

namespace Database\Factories;

use App\Models\LegacyFeeType;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LegacyFeeType> */
class LegacyFeeTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake()->unique()->words(2, true),
            'academic_year' => now()->year.'-'.(now()->year + 1),
            'amount_cents' => fake()->numberBetween(10000, 500000),
            'status' => 'active',
        ];
    }
}
