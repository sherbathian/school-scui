<?php

namespace Database\Factories;

use App\Models\LeaveType;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveType> */
class LeaveTypeFactory extends Factory
{
    public function definition(): array
    {
        return ['school_id' => School::factory(), 'title' => fake()->unique()->word()];
    }
}
