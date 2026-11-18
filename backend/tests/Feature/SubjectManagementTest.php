<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_create_subjects_assigned_to_grades_and_update_assignments(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $firstGrade = Grade::factory()->for($school)->create(['title' => 'Grade 1']);
        $secondGrade = Grade::factory()->for($school)->create(['title' => 'Grade 2']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/subjects', ['title' => 'Science', 'grade_ids' => [$firstGrade->id]])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Science')
            ->assertJsonPath('data.grades.0.title', 'Grade 1');

        $subject = Subject::where('school_id', $school->id)->where('title', 'Science')->firstOrFail();
        $this->putJson('/api/schools/'.$school->id.'/subjects/'.$subject->id, ['grade_ids' => [$secondGrade->id]])
            ->assertOk()
            ->assertJsonPath('data.grades.0.title', 'Grade 2');

        $this->assertDatabaseMissing('grade_subject', ['subject_id' => $subject->id, 'grade_id' => $firstGrade->id]);
        $this->assertDatabaseHas('grade_subject', ['school_id' => $school->id, 'subject_id' => $subject->id, 'grade_id' => $secondGrade->id]);
    }

    public function test_subject_assignment_rejects_foreign_school_and_inactive_grades(): void
    {
        $school = School::factory()->create();
        $foreignSchool = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $foreignGrade = Grade::factory()->for($foreignSchool)->create();
        $inactiveGrade = Grade::factory()->for($school)->create(['status' => 'inactive']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/subjects', ['title' => 'Science', 'grade_ids' => [$foreignGrade->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grade_ids');

        $this->postJson('/api/schools/'.$school->id.'/subjects', ['title' => 'Mathematics', 'grade_ids' => [$inactiveGrade->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grade_ids');

        $this->assertDatabaseMissing('subjects', ['school_id' => $school->id]);
    }

    public function test_database_rejects_grade_subject_link_with_mismatched_school_id(): void
    {
        $school = School::factory()->create();
        $foreignSchool = School::factory()->create();
        $grade = Grade::factory()->for($school)->create();
        $subject = Subject::factory()->for($foreignSchool)->create();

        $this->expectException(QueryException::class);
        DB::table('grade_subject')->insert([
            'school_id' => $school->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_teacher_can_view_subjects_but_cannot_create_or_archive_them(): void
    {
        $school = School::factory()->create();
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $subject = Subject::factory()->for($school)->create(['title' => 'History']);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/schools/'.$school->id.'/subjects')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'History');

        $this->postJson('/api/schools/'.$school->id.'/subjects', ['title' => 'Art'])->assertForbidden();
        $this->deleteJson('/api/schools/'.$school->id.'/subjects/'.$subject->id)->assertForbidden();
    }

    public function test_duplicate_subject_titles_are_school_scoped_and_archive_preserves_assignments(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $grade = Grade::factory()->for($school)->create();
        $subject = Subject::factory()->for($school)->create(['title' => 'Art']);
        $subject->grades()->syncWithPivotValues([$grade->id], ['school_id' => $school->id]);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/subjects', ['title' => 'Art'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->deleteJson('/api/schools/'.$school->id.'/subjects/'.$subject->id)->assertNoContent();
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('grade_subject', ['subject_id' => $subject->id, 'grade_id' => $grade->id]);
    }

    private function schoolAdminFor(School $school): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }
}
