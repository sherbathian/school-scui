<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\School;
use App\Models\SchoolSession;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_school_scoped_lookup_values_and_a_staff_profile(): void
    {
        $school = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $designation = Designation::factory()->for($school)->create();
        $department = Department::factory()->for($school)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/schools/{$school->id}/staff-lookups/designations")
            ->assertOk()->assertJsonPath('data.0.title', $designation->title);
        $leaveResponse = $this->postJson("/api/schools/{$school->id}/staff-lookups/leave-types", ['title' => 'Annual'])
            ->assertCreated()->assertJsonPath('data.title', 'Annual');

        $response = $this->postJson("/api/schools/{$school->id}/staff", $this->staffPayload($designation, $department))
            ->assertCreated()->assertJsonPath('data.staff_no', 'ST-100');
        $this->assertDatabaseHas('staff', ['id' => $response->json('data.id'), 'school_id' => $school->id]);
        $this->assertDatabaseHas('leave_types', ['id' => $leaveResponse->json('data.id'), 'school_id' => $school->id]);
    }

    public function test_cross_school_lookup_and_unlinked_user_ids_are_rejected(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $foreignDesignation = Designation::factory()->for($otherSchool)->create();
        $department = Department::factory()->for($school)->create();
        $payload = $this->staffPayload($foreignDesignation, $department);

        $this->actingAs($admin, 'sanctum')->postJson("/api/schools/{$school->id}/staff", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('designation_id');

        $designation = Designation::factory()->for($school)->create();
        $unlinkedUser = User::factory()->create();
        $payload = $this->staffPayload($designation, $department) + ['user_id' => $unlinkedUser->id];
        $this->postJson("/api/schools/{$school->id}/staff", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_staff_login_is_optional_but_can_only_be_linked_once(): void
    {
        $school = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'teacher']);
        $designation = Designation::factory()->for($school)->create();
        $department = Department::factory()->for($school)->create();
        $payload = $this->staffPayload($designation, $department);

        $this->actingAs($admin, 'sanctum')->postJson("/api/schools/{$school->id}/staff", $payload + ['user_id' => $user->id])->assertCreated();
        $payload['staff_no'] = 'ST-101';
        $this->postJson("/api/schools/{$school->id}/staff", $payload + ['user_id' => $user->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_sensitive_staff_fields_are_encrypted_and_hidden_from_teachers(): void
    {
        $school = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $designation = Designation::factory()->for($school)->create();
        $department = Department::factory()->for($school)->create();
        $payload = $this->staffPayload($designation, $department) + ['bank_account_number' => 'PK00-SECRET-123'];

        $created = $this->actingAs($admin, 'sanctum')->postJson("/api/schools/{$school->id}/staff", $payload)->assertCreated();
        $id = $created->json('data.id');
        $stored = DB::table('staff')->where('id', $id)->value('bank_account_number');
        $this->assertNotSame('PK00-SECRET-123', $stored);
        $this->assertSame('PK00-SECRET-123', Staff::findOrFail($id)->bank_account_number);

        $this->actingAs($teacher, 'sanctum')->getJson("/api/schools/{$school->id}/staff/{$id}")
            ->assertOk()->assertJsonMissingPath('data.cnic')->assertJsonMissingPath('data.basic_salary')
            ->assertJsonMissingPath('data.bank_account_number')->assertJsonMissingPath('data.phone');
    }

    public function test_staff_lookup_names_are_unique_per_school_and_in_use_items_cannot_be_deleted(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $designation = Designation::factory()->for($school)->create(['title' => 'Teacher']);
        $otherDesignation = Designation::factory()->for($otherSchool)->create(['title' => 'Teacher']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/schools/{$school->id}/staff-lookups/designations", ['title' => 'Teacher'])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->assertSame('Teacher', $otherDesignation->title);

        $department = Department::factory()->for($school)->create();
        $person = Staff::factory()->for($school)->create(['designation_id' => $designation->id, 'department_id' => $department->id]);
        $this->deleteJson("/api/schools/{$school->id}/staff-lookups/designations/{$designation->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('lookup');
        $this->assertDatabaseHas('staff', ['id' => $person->id]);
    }

    public function test_teacher_can_read_staff_but_cannot_change_staff_or_lookups(): void
    {
        $school = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $teacher = User::factory()->create();
        $teacher->schools()->attach($school, ['role' => 'teacher']);
        $designation = Designation::factory()->for($school)->create();
        $department = Department::factory()->for($school)->create();
        $person = Staff::factory()->for($school)->create(['designation_id' => $designation->id, 'department_id' => $department->id]);

        $this->actingAs($teacher, 'sanctum')->getJson("/api/schools/{$school->id}/staff")->assertOk();
        $this->postJson("/api/schools/{$school->id}/staff-lookups/departments", ['title' => 'Science'])->assertForbidden();
        $this->patchJson("/api/schools/{$school->id}/staff/{$person->id}", ['status' => 'inactive'])->assertForbidden();
        $this->assertSame('active', $person->fresh()->status);
    }

    public function test_leave_balance_is_per_staff_leave_type_and_session_and_rejects_invalid_values(): void
    {
        $school = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $designation = Designation::factory()->for($school)->create();
        $department = Department::factory()->for($school)->create();
        $leaveType = LeaveType::factory()->for($school)->create();
        $session = SchoolSession::factory()->for($school)->create();
        $person = Staff::factory()->for($school)->create(['designation_id' => $designation->id, 'department_id' => $department->id]);
        $path = "/api/schools/{$school->id}/staff/{$person->id}/leave-balances";

        $this->actingAs($admin, 'sanctum')->postJson($path, [
            'leave_type_id' => $leaveType->id, 'school_session_id' => $session->id, 'allocated' => 12, 'used' => 2.5,
        ])->assertCreated()->assertJsonPath('data.used', '2.5');
        $this->postJson($path, [
            'leave_type_id' => $leaveType->id, 'school_session_id' => $session->id, 'allocated' => 12, 'used' => 2.5,
        ])->assertUnprocessable();
        $this->postJson($path, [
            'leave_type_id' => $leaveType->id, 'school_session_id' => $session->id, 'allocated' => 2, 'used' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('used');
    }

    public function test_staff_and_leave_balance_routes_are_scoped_to_school(): void
    {
        $school = School::factory()->create();
        $foreign = School::factory()->create();
        $admin = $this->staffAdmin($school);
        $designation = Designation::factory()->for($foreign)->create();
        $department = Department::factory()->for($foreign)->create();
        $person = Staff::factory()->for($foreign)->create(['designation_id' => $designation->id, 'department_id' => $department->id]);

        $this->actingAs($admin, 'sanctum')->getJson("/api/schools/{$foreign->id}/staff")->assertNotFound();
        $this->getJson("/api/schools/{$school->id}/staff/{$person->id}")->assertNotFound();
    }

    private function staffAdmin(School $school): User
    {
        $user = User::factory()->create();
        $user->schools()->attach($school, ['role' => 'school_admin']);

        return $user;
    }

    /** @return array<string, mixed> */
    private function staffPayload(Designation $designation, Department $department): array
    {
        return [
            'designation_id' => $designation->id,
            'department_id' => $department->id,
            'staff_no' => 'ST-100',
            'first_name' => 'Amina',
            'last_name' => 'Khan',
            'gender' => 'female',
            'marital_status' => 'single',
            'birth_date' => '1995-04-20',
            'joining_date' => '2026-08-01',
            'email' => 'amina@example.test',
            'phone' => '03001234567',
            'cnic' => '1234512345671',
            'basic_salary' => '50000.00',
        ];
    }
}
