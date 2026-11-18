<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'grade_id' => Grade::factory(),
            'section_id' => Section::factory(),
            'academic_year' => '2026-2027',
            'status' => 'active',
            'enrolled_on' => now()->toDateString(),
            'ended_on' => null,
        ];
    }
}
