<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_create_grades_and_assign_sections(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $section = Section::factory()->for($school)->create(['title' => 'Section A']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/grades', [
                'title' => 'Grade 1',
                'section_ids' => [$section->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Grade 1')
            ->assertJsonPath('data.sections.0.title', 'Section A');

        $grade = Grade::where('school_id', $school->id)->where('title', 'Grade 1')->firstOrFail();
        $this->assertDatabaseHas('grade_section', [
            'school_id' => $school->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_duplicate_grade_and_section_titles_are_rejected_within_one_school(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        Grade::factory()->for($school)->create(['title' => 'Grade 1']);
        Section::factory()->for($school)->create(['title' => 'Section A']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/grades', ['title' => 'Grade 1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->postJson('/api/schools/'.$school->id.'/sections', ['title' => 'Section A'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $otherSchool = School::factory()->create();
        $otherGrade = Grade::factory()->for($otherSchool)->create(['title' => 'Grade 1']);
        $this->assertModelExists($otherGrade);
    }

    public function test_section_assignment_rejects_another_schools_section(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $foreignSection = Section::factory()->for($otherSchool)->create();

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/grades', [
                'title' => 'Grade 1',
                'section_ids' => [$foreignSection->id],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.section_ids.0', 'Choose only active sections from this school.');

        $this->assertDatabaseMissing('grades', ['school_id' => $school->id, 'title' => 'Grade 1']);
    }

    public function test_database_rejects_a_grade_section_link_with_mismatched_school_id(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $grade = Grade::factory()->for($school)->create();
        $section = Section::factory()->for($otherSchool)->create();

        $this->expectException(QueryException::class);

        DB::table('grade_section')->insert([
            'school_id' => $school->id,
            'grade_id' => $grade->id,
            'section_id' => $section->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_grade_section_assignments_can_be_changed_and_archived_without_losing_the_link_history(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $firstSection = Section::factory()->for($school)->create(['title' => 'Section A']);
        $secondSection = Section::factory()->for($school)->create(['title' => 'Section B']);
        $grade = Grade::factory()->for($school)->create(['title' => 'Grade 2']);
        $grade->sections()->syncWithPivotValues([$firstSection->id], ['school_id' => $school->id]);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->putJson('/api/schools/'.$school->id.'/grades/'.$grade->id.'/sections', [
                'section_ids' => [$secondSection->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.sections.0.title', 'Section B');

        $this->deleteJson('/api/schools/'.$school->id.'/grades/'.$grade->id)->assertNoContent();
        $this->assertDatabaseHas('grades', ['id' => $grade->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('grade_section', ['grade_id' => $grade->id, 'section_id' => $secondSection->id]);
    }

    public function test_teacher_can_read_but_cannot_change_academic_structure(): void
    {
        $school = School::factory()->create();
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $section = Section::factory()->for($school)->create();
        Grade::factory()->for($school)->create(['title' => 'Grade 3']);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/schools/'.$school->id.'/grades')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Grade 3');

        $this->postJson('/api/schools/'.$school->id.'/sections', ['title' => 'Section B'])
            ->assertForbidden();

        $otherSchool = School::factory()->create();
        $otherGrade = Grade::factory()->for($otherSchool)->create();
        $this->getJson('/api/schools/'.$school->id.'/grades/'.$otherGrade->id)->assertNotFound();
    }

    public function test_archiving_a_section_preserves_its_existing_grade_assignment(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = $this->schoolAdminFor($school);
        $section = Section::factory()->for($school)->create();
        $grade = Grade::factory()->for($school)->create();
        $grade->sections()->syncWithPivotValues([$section->id], ['school_id' => $school->id]);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->deleteJson('/api/schools/'.$school->id.'/sections/'.$section->id)
            ->assertNoContent();

        $this->assertDatabaseHas('sections', ['id' => $section->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('grade_section', ['grade_id' => $grade->id, 'section_id' => $section->id]);
    }

    private function schoolAdminFor(School $school): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }
}
