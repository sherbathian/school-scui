<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_school_wide_fee_types_and_discounts(): void
    {
        $school = School::factory()->create();
        $admin = $this->feeAdmin($school);
        $this->actingAs($admin, 'sanctum');

        $type = $this->postJson("/api/schools/{$school->id}/fee-types", [
            'title' => 'Tuition', 'description' => 'Monthly tuition fee',
        ])->assertCreated()->assertJsonPath('data.title', 'Tuition')->assertJsonPath('data.description', 'Monthly tuition fee');
        $typeId = $type->json('data.id');
        $this->postJson("/api/schools/{$school->id}/fee-types", ['title' => 'Tuition'])
            ->assertUnprocessable()->assertJsonValidationErrors('title');

        $this->postJson("/api/schools/{$school->id}/fee-discounts", [
            'title' => 'Sibling 10%', 'discount_type' => 'percentage', 'value' => '10.00',
        ])->assertCreated()->assertJsonPath('data.value', '10.00');
        $this->postJson("/api/schools/{$school->id}/fee-discounts", [
            'title' => 'Invalid', 'discount_type' => 'percentage', 'value' => '110.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('value');

        $this->assertDatabaseHas('fee_types', ['id' => $typeId, 'school_id' => $school->id, 'title' => 'Tuition']);
    }

    public function test_fee_type_catalog_is_school_scoped_and_teachers_cannot_access_it(): void
    {
        $school = School::factory()->create();
        $foreign = School::factory()->create();
        $admin = $this->feeAdmin($school);
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $type = $foreign->feeTypes()->create(['title' => 'Books']);

        $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$school->id}/fee-types")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/schools/{$foreign->id}/fee-types")->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->getJson("/api/schools/{$school->id}/fee-types")->assertForbidden();
        $this->postJson("/api/schools/{$school->id}/fee-types", ['title' => 'Books'])->assertForbidden();
    }

    public function test_admin_can_assign_fee_group_to_school_grades_and_foreign_grade_is_rejected(): void
    {
        $school = School::factory()->create();
        $foreign = School::factory()->create();
        $admin = $this->feeAdmin($school);
        $grade = Grade::factory()->for($school)->create(['title' => 'Grade 1']);
        $otherGrade = Grade::factory()->for($foreign)->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/schools/{$school->id}/fee-groups", [
                'title' => 'Primary', 'description' => 'Primary grades', 'allows_discount' => false, 'grade_ids' => [$grade->id],
            ])->assertCreated()->assertJsonPath('data.allows_discount', false)->assertJsonPath('data.grades.0.title', 'Grade 1');
        $this->postJson("/api/schools/{$school->id}/fee-groups", ['title' => 'Foreign', 'grade_ids' => [$otherGrade->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('grade_ids');
    }

    public function test_fee_master_requires_same_school_references_and_decimal_values(): void
    {
        $school = School::factory()->create();
        $foreign = School::factory()->create();
        $admin = $this->feeAdmin($school);
        $session = SchoolSession::factory()->for($school)->create(['is_current' => true]);
        $foreignSession = SchoolSession::factory()->for($foreign)->create();
        $grade = Grade::factory()->for($school)->create();
        $group = $school->feeGroups()->create(['title' => 'Primary', 'allows_discount' => true]);
        $foreignGroup = $foreign->feeGroups()->create(['title' => 'Foreign', 'allows_discount' => true]);
        $type = $school->feeTypes()->create(['title' => 'Tuition']);
        $foreignType = $foreign->feeTypes()->create(['title' => 'Foreign tuition']);
        $group->grades()->syncWithPivotValues([$grade->id], ['school_id' => $school->id]);
        $payload = ['school_session_id' => $session->id, 'fee_group_id' => $group->id, 'fee_type_id' => $type->id,
            'billing_month' => '2026-09', 'due_date' => '2026-09-01', 'amount' => '1250.50', 'fine_type' => 'percentage', 'fine_value' => '5.00'];

        $this->actingAs($admin, 'sanctum')->postJson("/api/schools/{$school->id}/fee-masters", $payload)
            ->assertCreated()->assertJsonPath('data.amount', '1250.50')->assertJsonPath('data.fine_value', '5.00');
        $this->postJson("/api/schools/{$school->id}/fee-masters", [...$payload, 'fine_value' => '105'])
            ->assertUnprocessable()->assertJsonValidationErrors('fine_value');
        $this->postJson("/api/schools/{$school->id}/fee-masters", [...$payload, 'school_session_id' => $foreignSession->id])
            ->assertUnprocessable()->assertJsonValidationErrors('school_session_id');
        $this->postJson("/api/schools/{$school->id}/fee-masters", [...$payload, 'fee_group_id' => $foreignGroup->id])
            ->assertUnprocessable()->assertJsonValidationErrors('fee_group_id');
        $this->postJson("/api/schools/{$school->id}/fee-masters", [...$payload, 'fee_type_id' => $foreignType->id])
            ->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        $this->postJson("/api/schools/{$school->id}/fee-masters", [...$payload, 'amount' => '-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_fee_master_is_unique_per_group_type_session_and_month_and_protects_used_groups(): void
    {
        $school = School::factory()->create();
        $admin = $this->feeAdmin($school);
        $session = SchoolSession::factory()->for($school)->create(['is_current' => true]);
        $group = $school->feeGroups()->create(['title' => 'Primary', 'allows_discount' => true]);
        $type = $school->feeTypes()->create(['title' => 'Tuition']);
        $path = "/api/schools/{$school->id}/fee-masters";
        $payload = ['school_session_id' => $session->id, 'fee_group_id' => $group->id, 'fee_type_id' => $type->id,
            'billing_month' => '2026-09', 'due_date' => '2026-09-01', 'amount' => '100.00'];
        $this->actingAs($admin, 'sanctum')->postJson($path, $payload)->assertCreated();
        $this->postJson($path, $payload)->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        $this->deleteJson("/api/schools/{$school->id}/fee-groups/{$group->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('fee_group');
    }

    public function test_student_profile_includes_the_academic_year_of_a_monthly_charge(): void
    {
        $school = School::factory()->create();
        $student = Student::factory()->for($school)->create();
        $session = SchoolSession::factory()->for($school)->create(['starts_on' => '2026-08-01', 'ends_on' => '2027-06-30']);
        $group = $school->feeGroups()->create(['title' => 'Primary']);
        $type = $school->feeTypes()->create(['title' => 'Tuition']);
        $dates = ['school_session_id' => $session->id, 'billing_month' => '2026-09', 'due_date' => '2026-09-15'];
        $master = $school->feeMasters()->create([...$dates, 'fee_group_id' => $group->id, 'fee_type_id' => $type->id, 'amount' => '3000.00']);
        $student->feeCharges()->create([...$dates, 'school_id' => $school->id, 'fee_master_id' => $master->id, 'amount' => '3000.00']);

        $this->actingAs($this->feeAdmin($school), 'sanctum')
            ->getJson("/api/schools/{$school->id}/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.fee_history.0.kind', 'monthly')
            ->assertJsonPath('data.fee_history.0.fee_type', 'Tuition')
            ->assertJsonPath('data.fee_history.0.academic_year', '2026-2027');
    }

    private function feeAdmin(School $school): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }
}
