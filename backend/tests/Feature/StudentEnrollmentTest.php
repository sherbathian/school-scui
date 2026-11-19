<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_create_a_student_and_enroll_them_in_an_assigned_class(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        [$grade, $section] = $this->classFor($school);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/students', [
                'admission_number' => 'N-1001',
                'first_name' => 'Amina',
                'last_name' => 'Khan',
            ])
            ->assertCreated()
            ->assertJsonPath('data.admission_number', 'N-1001')
            ->assertJsonPath('data.current_enrollment', null);

        $student = Student::where('school_id', $school->id)->where('admission_number', 'N-1001')->firstOrFail();
        $this->postJson('/api/schools/'.$school->id.'/students/'.$student->id.'/enrollments', [
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
        ])
            ->assertCreated()
            ->assertJsonPath('data.grade.title', 'Grade 1')
            ->assertJsonPath('data.section.title', 'Blue');

        $this->getJson('/api/schools/'.$school->id.'/students')
            ->assertOk()
            ->assertJsonPath('data.0.current_enrollment.academic_year', '2026-2027');
    }

    public function test_student_admission_saves_erd_profile_and_parent_guardian_details(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/students', [
                'admission_number' => 'N-ERD-1',
                'first_name' => 'Amina',
                'last_name' => 'Khan',
                'gender' => 'female',
                'birth_date' => '2016-04-12',
                'admission_date' => '2026-08-15',
                'phone' => '03001234567',
                'address' => 'School town',
                'guardian' => ['father_name' => 'Imran Khan', 'father_phone' => '03007654321'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.guardian.father_name', 'Imran Khan')
            ->assertJsonPath('data.guardian.father_phone', '03007654321');

        $this->assertDatabaseHas('students', ['admission_number' => 'N-ERD-1', 'address' => 'School town']);
        $this->assertDatabaseHas('guardians', ['school_id' => $school->id, 'father_name' => 'Imran Khan']);
    }

    public function test_enrollment_requires_a_section_assigned_to_an_active_grade_in_the_same_school(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $student = Student::factory()->for($school)->create();
        $grade = Grade::factory()->for($school)->create();
        $unassignedSection = Section::factory()->for($school)->create();
        $foreignSection = Section::factory()->for($otherSchool)->create();

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/students/'.$student->id.'/enrollments', [
                'grade_id' => $grade->id,
                'section_id' => $unassignedSection->id,
                'academic_year' => '2026-2027',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section_id');

        $this->postJson('/api/schools/'.$school->id.'/students/'.$student->id.'/enrollments', [
            'grade_id' => $grade->id,
            'section_id' => $foreignSection->id,
            'academic_year' => '2026-2027',
        ])->assertUnprocessable()->assertJsonValidationErrors('section_id');

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_moving_a_student_closes_the_previous_enrollment_and_keeps_history(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $student = Student::factory()->for($school)->create();
        [$firstGrade, $firstSection] = $this->classFor($school, 'Grade 1', 'Blue');
        [$secondGrade, $secondSection] = $this->classFor($school, 'Grade 2', 'Green');

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/students/'.$student->id.'/enrollments', [
                'grade_id' => $firstGrade->id,
                'section_id' => $firstSection->id,
                'academic_year' => '2026-2027',
            ])->assertCreated();

        $this->postJson('/api/schools/'.$school->id.'/students/'.$student->id.'/enrollments', [
            'grade_id' => $secondGrade->id,
            'section_id' => $secondSection->id,
            'academic_year' => '2026-2027',
        ])->assertCreated();

        $this->getJson('/api/schools/'.$school->id.'/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('data.current_enrollment.grade.title', 'Grade 2')
            ->assertJsonCount(2, 'data.enrollments')
            ->assertJsonPath('data.enrollments.1.status', 'completed');

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'grade_id' => $firstGrade->id,
            'ended_on' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_admission_numbers_are_unique_per_school_and_teacher_access_is_read_only(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $student = Student::factory()->for($school)->create(['admission_number' => 'N-2001']);
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/students', [
                'admission_number' => 'N-2001',
                'first_name' => 'Sara',
                'last_name' => 'Ali',
            ])->assertUnprocessable()->assertJsonValidationErrors('admission_number');

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/schools/'.$school->id.'/students')
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id);

        $this->postJson('/api/schools/'.$school->id.'/students', [
            'admission_number' => 'N-2002',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
        ])->assertForbidden();
    }

    public function test_archived_students_can_be_restored_and_roster_can_filter_by_academic_year(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        [$grade, $section] = $this->classFor($school);
        $archivedStudent = Student::factory()->for($school)->create(['status' => 'active']);
        $otherYearStudent = Student::factory()->for($school)->create();

        $session = $school->schoolSessions()->where('is_current', true)->firstOrFail();
        $session->update(['starts_on' => '2025-08-01', 'ends_on' => '2026-06-30']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/students/'.$archivedStudent->id.'/enrollments', [
                'grade_id' => $grade->id,
                'section_id' => $section->id,
                'academic_year' => '2025-2026',
            ])->assertCreated();

        $this->deleteJson('/api/schools/'.$school->id.'/students/'.$archivedStudent->id)->assertNoContent();
        $this->patchJson('/api/schools/'.$school->id.'/students/'.$archivedStudent->id, ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.current_enrollment', null);

        $session->update(['starts_on' => '2026-08-01', 'ends_on' => '2027-06-30']);
        $this->postJson('/api/schools/'.$school->id.'/students/'.$otherYearStudent->id.'/enrollments', [
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
        ])->assertCreated();

        $this->getJson('/api/schools/'.$school->id.'/students?academic_year=2025-2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archivedStudent->id)
            ->assertJsonPath('data.0.enrollments.0.academic_year', '2025-2026');
    }

    /** @return array{Grade, Section} */
    private function classFor(School $school, string $gradeTitle = 'Grade 1', string $sectionTitle = 'Blue'): array
    {
        $grade = Grade::factory()->for($school)->create(['title' => $gradeTitle]);
        $section = Section::factory()->for($school)->create(['title' => $sectionTitle]);
        $grade->sections()->syncWithPivotValues([$section->id], ['school_id' => $school->id]);

        return [$grade, $section];
    }

    private function schoolAdminFor(School $school): User
    {
        SchoolSession::factory()->for($school)->create([
            'starts_on' => '2026-08-01', 'ends_on' => '2027-06-30', 'is_current' => true,
        ]);
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }
}
