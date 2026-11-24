<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_branch_operations_opens_with_the_same_permissions_as_the_explicit_branch(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        $user = $this->member($school, 'teacher');
        $this->actingAs($user)->get('/admin/operations')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/BranchWorkspace')->where('branch.id', $school->id)->has('branches', 1)
            ->has('allowedModules', 1)->where('allowedModules.0', 'attendance'));
        $this->get("/admin/branches/{$other->id}/operations")->assertNotFound();
        $this->get('/admin/members')->assertForbidden();

        $user->schools()->attach($other, ['role' => 'teacher', 'is_active' => true]);
        $this->get('/admin/operations')->assertInertia(fn (Assert $page) => $page
            ->where('branch', null)->has('branches', 2));
    }

    public function test_branch_roles_can_sign_in_and_receive_only_their_dashboard_and_branch(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        foreach (['admin' => 'Branch Admin', 'teacher' => 'Teacher', 'account' => 'Accountant', 'student' => 'Student'] as $key => $label) {
            $user = $this->member($school, $key);

            $this->withHeader('Origin', 'http://localhost:8000')
                ->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'password'])
                ->assertOk()->assertJsonPath('data.is_super_admin', false);

            $this->get('/admin/login')->assertRedirect('/admin/dashboard');
            $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')->where('role', $label)->where('platform', false)
                ->where('branch.id', $school->id)->has('branches', 1));
            $this->get("/admin/dashboard/{$other->id}")->assertNotFound();
            $this->get('/admin/listings')->assertForbidden();
            $this->getJson('/api/admin/listings')->assertForbidden();

            if ($key !== 'student') {
                $this->get("/admin/branches/{$school->id}/operations")->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/BranchWorkspace')->where('schoolAccess', $key === 'admin' ? ['members', 'roles'] : []));
            }

            if ($key === 'student') {
                $this->get("/admin/branches/{$school->id}/operations")->assertForbidden();
                $this->getJson("/api/schools/{$school->id}/students")->assertForbidden();
            }

            $this->postJson('/api/logout')->assertNoContent();
        }
    }

    public function test_branch_admin_can_link_student_to_a_record_in_their_branch(): void
    {
        $this->withoutVite();
        $school = School::factory()->create();
        $other = School::factory()->create();
        $admin = $this->member($school, 'admin');
        $student = $this->member($school, 'student');
        $record = Student::factory()->for($school)->create();
        $foreignRecord = Student::factory()->for($other)->create();

        $this->actingAs($admin);
        foreach (['/admin/members', '/admin/roles', "/admin/branches/{$school->id}/members", "/admin/branches/{$school->id}/roles"] as $url) {
            $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Admin/Access')
                ->where('schoolAccess', ['members', 'roles'])->where('operations', true));
        }
        $this->get("/admin/branches/{$other->id}/members")->assertNotFound();
        $this->patchJson("/api/schools/{$school->id}/users/{$student->id}", [
            'student_admission_number' => $foreignRecord->admission_number,
        ])->assertUnprocessable()->assertJsonValidationErrors('student_admission_number');
        $this->patchJson("/api/schools/{$school->id}/users/{$student->id}", [
            'student_admission_number' => $record->admission_number,
        ])->assertOk()->assertJsonPath('data.student_admission_number', $record->admission_number);

        $this->actingAs($student)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('studentLinked', true)->where('cards.My unpaid fee invoices', 0));
    }

    public function test_assigned_role_users_are_visible_only_within_the_authorized_branch(): void
    {
        $school = School::factory()->create();
        $other = School::factory()->create();
        $admin = $this->member($school, 'admin');
        $teacher = $this->member($school, 'teacher');
        $this->member($other, 'teacher');

        $roles = Role::query();
        $teacherRole = (clone $roles)->where('team_id', $school->id)->where('name', 'teacher')->firstOrFail();
        $foreignRole = (clone $roles)->where('team_id', $other->id)->where('name', 'teacher')->firstOrFail();
        $platformRole = $roles->whereNull('team_id')->where('name', 'super-admin')->firstOrFail();
        $path = "/api/schools/{$school->id}/roles";

        $this->actingAs($admin)->getJson("{$path}/{$teacherRole->id}/assigned-users")->assertExactJson([
            'data' => [['id' => $teacher->id, 'name' => $teacher->name, 'email' => $teacher->email, 'is_active' => true]],
        ]);
        $this->getJson("{$path}/{$foreignRole->id}/assigned-users")->assertNotFound();
        $this->getJson("{$path}/{$platformRole->id}/assigned-users")->assertNotFound();
        $this->actingAs($teacher)->getJson("{$path}/{$teacherRole->id}/assigned-users")->assertForbidden();
    }

    private function member(School $school, string $role): User
    {
        app(SchoolRoles::class)->ensureDefaults($school);
        $user = User::factory()->create(['is_active' => true]);
        $user->schools()->attach($school, ['role' => $role, 'is_active' => true]);
        setPermissionsTeamId($school->id);
        $user->assignRole($role);
        setPermissionsTeamId(null);

        return $user;
    }
}
