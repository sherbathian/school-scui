<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_record_and_update_attendance_for_a_full_class(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolUserFor($school, 'school_admin');
        [$grade, $section] = $this->classFor($school);
        $firstStudent = Student::factory()->for($school)->create(['first_name' => 'Amina', 'last_name' => 'Ali']);
        $secondStudent = Student::factory()->for($school)->create(['first_name' => 'Bilal', 'last_name' => 'Khan']);
        $firstEnrollment = $this->enroll($firstStudent, $school, $grade, $section);
        $secondEnrollment = $this->enroll($secondStudent, $school, $grade, $section);
        $attendanceDate = now()->toDateString();
        $payload = [
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'attendance_date' => $attendanceDate,
            'records' => [
                ['enrollment_id' => $firstEnrollment->id, 'status' => 'present'],
                ['enrollment_id' => $secondEnrollment->id, 'status' => 'late'],
            ],
        ];

        $this->actingAs($schoolAdmin, 'sanctum')
            ->getJson($this->attendanceUrl($school, $grade, $section, $attendanceDate))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', null);

        $this->putJson('/api/schools/'.$school->id.'/attendance', $payload)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('attendance_records', 2);
        $this->putJson('/api/schools/'.$school->id.'/attendance', [
            ...$payload,
            'records' => [
                ['enrollment_id' => $firstEnrollment->id, 'status' => 'absent'],
                ['enrollment_id' => $secondEnrollment->id, 'status' => 'excused'],
            ],
        ])->assertOk()->assertJsonPath('data.0.status', 'absent')->assertJsonPath('data.1.status', 'excused');

        $this->assertDatabaseCount('attendance_records', 2);
    }

    public function test_teacher_can_record_attendance_but_unassigned_user_cannot(): void
    {
        $school = School::factory()->create();
        $teacher = $this->schoolUserFor($school, 'teacher');
        $outsideUser = User::factory()->create();
        [$grade, $section] = $this->classFor($school);
        $student = Student::factory()->for($school)->create();
        $enrollment = $this->enroll($student, $school, $grade, $section);
        $payload = [
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'attendance_date' => now()->toDateString(),
            'records' => [['enrollment_id' => $enrollment->id, 'status' => 'present']],
        ];

        $this->actingAs($teacher, 'sanctum')
            ->putJson('/api/schools/'.$school->id.'/attendance', $payload)
            ->assertOk();

        $this->actingAs($outsideUser, 'sanctum')
            ->putJson('/api/schools/'.$school->id.'/attendance', $payload)
            ->assertNotFound();
    }

    public function test_attendance_rejects_incomplete_class_lists_foreign_enrollments_and_invalid_statuses(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolUserFor($school, 'school_admin');
        [$grade, $section] = $this->classFor($school);
        $firstEnrollment = $this->enroll(Student::factory()->for($school)->create(), $school, $grade, $section);
        $secondEnrollment = $this->enroll(Student::factory()->for($school)->create(), $school, $grade, $section);
        $foreignSchool = School::factory()->create();
        [$foreignGrade, $foreignSection] = $this->classFor($foreignSchool);
        $foreignEnrollment = $this->enroll(Student::factory()->for($foreignSchool)->create(), $foreignSchool, $foreignGrade, $foreignSection);
        $payload = [
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'attendance_date' => now()->toDateString(),
        ];

        $this->actingAs($schoolAdmin, 'sanctum')
            ->putJson('/api/schools/'.$school->id.'/attendance', [
                ...$payload,
                'records' => [['enrollment_id' => $firstEnrollment->id, 'status' => 'present']],
            ])->assertUnprocessable()->assertJsonValidationErrors('records');

        $this->putJson('/api/schools/'.$school->id.'/attendance', [
            ...$payload,
            'records' => [
                ['enrollment_id' => $firstEnrollment->id, 'status' => 'present'],
                ['enrollment_id' => $foreignEnrollment->id, 'status' => 'present'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('records');

        $this->putJson('/api/schools/'.$school->id.'/attendance', [
            ...$payload,
            'records' => [
                ['enrollment_id' => $firstEnrollment->id, 'status' => 'unknown'],
                ['enrollment_id' => $secondEnrollment->id, 'status' => 'present'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('records.0.status');

        $this->assertDatabaseCount('attendance_records', 0);
    }

    private function classFor(School $school): array
    {
        $grade = Grade::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create();
        $grade->sections()->syncWithPivotValues([$section->id], ['school_id' => $school->id]);

        return [$grade, $section];
    }

    private function enroll(Student $student, School $school, Grade $grade, Section $section): Enrollment
    {
        return $student->enrollments()->create([
            'school_id' => $school->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'status' => 'active',
            'enrolled_on' => now()->toDateString(),
        ]);
    }

    private function schoolUserFor(School $school, string $role): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => $role]);

        return $user;
    }

    private function attendanceUrl(School $school, Grade $grade, Section $section, string $date): string
    {
        return '/api/schools/'.$school->id.'/attendance?grade_id='.$grade->id
            .'&section_id='.$section->id.'&academic_year=2026-2027&attendance_date='.$date;
    }
}
