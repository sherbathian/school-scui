<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolSession> */
class SchoolSessionFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', '+2 years');

        return [
            'school_id' => School::factory(),
            'title' => fake()->unique()->year().'-'.fake()->unique()->year(),
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => (clone $start)->modify('+10 months')->format('Y-m-d'),
            'is_current' => false,
            'current_marker' => null,
            'status' => 'active',
        ];
    }
}
