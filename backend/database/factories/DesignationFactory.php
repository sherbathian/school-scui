<?php

namespace Database\Factories;

use App\Models\Designation;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Designation> */
class DesignationFactory extends Factory
{
    public function definition(): array
    {
        return ['school_id' => School::factory(), 'title' => fake()->unique()->jobTitle()];
    }
}
