<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Designation;
use App\Models\School;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'designation_id' => Designation::factory()->for($school),
            'department_id' => Department::factory()->for($school),
            'staff_no' => fake()->unique()->bothify('ST-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'joining_date' => fake()->date(),
            'basic_salary' => '25000.00',
            'status' => 'active',
        ];
    }
}
