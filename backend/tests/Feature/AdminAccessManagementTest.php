<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_require_platform_access(): void
    {
        $branch = School::factory()->create();
        $this->withoutVite()->get('/admin/users')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get("/admin/branches/{$branch->id}/roles")->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/users')
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Access')->where('screen', 'users'));
        $this->get('/admin/permissions')
            ->assertInertia(fn (Assert $page) => $page->where('screen', 'permissions'));
        $this->get("/admin/branches/{$branch->id}/members")
            ->assertInertia(fn (Assert $page) => $page->where('branch.id', $branch->id)->where('screen', 'members'));
    }

    public function test_platform_admin_can_grant_and_remove_only_branch_scoped_direct_permissions(): void
    {
        $first = School::factory()->create();
        $second = School::factory()->create();
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->schools()->attach([$first->id => ['role' => 'teacher'], $second->id => ['role' => 'teacher']]);
        $path = "/api/schools/{$first->id}/users/{$teacher->id}";

        $manager = User::factory()->create();
        $manager->schools()->attach($first, ['role' => 'school_admin']);
        $this->actingAs($manager, 'sanctum')->patchJson($path, ['direct_permissions' => ['fees.view']])->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')->patchJson($path, ['direct_permissions' => ['users.create']])
            ->assertUnprocessable()->assertJsonValidationErrors('direct_permissions.0');
        $this->patchJson($path, ['direct_permissions' => ['fees.view']])
            ->assertOk()->assertJsonPath('data.direct_permissions.0', 'fees.view');

        $this->actingAs($teacher, 'sanctum')->getJson("/api/schools/{$first->id}/fee-types")->assertOk();
        $this->getJson("/api/schools/{$second->id}/fee-types")->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')->deleteJson($path)->assertNoContent();
        $this->assertDatabaseMissing('model_has_permissions', ['team_id' => $first->id, 'model_id' => $teacher->id]);
    }

    public function test_branch_role_assignment_cannot_use_a_role_from_another_branch(): void
    {
        $a = School::factory()->create();
        $b = School::factory()->create();
        $account = User::factory()->create();
        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/schools/{$b->id}/roles", [
            'name' => 'local-reporter', 'permissions' => ['reports.view'],
        ])->assertCreated();

        $this->postJson("/api/schools/{$a->id}/users", ['email' => $account->email, 'role' => 'local-reporter'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('school_user', ['school_id' => $a->id, 'user_id' => $account->id]);
        $this->assertTrue(DB::table('roles')->where('team_id', $b->id)->where('name', 'local-reporter')->exists());
    }

    public function test_platform_user_crud_protects_memberships_and_inactive_users_lose_api_access(): void
    {
        $admin = $this->admin();
        $school = School::factory()->create();
        $details = [
            'name' => 'Synthetic account', 'email' => 'synthetic-access@example.test',
            'phone' => '+1 202 555 0100', 'locale' => 'ur',
        ];
        $this->actingAs($admin, 'sanctum')->postJson('/api/users', [...$details, 'password' => 'Demo123'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/users', [...$details, 'password' => 'Demo1234'])
            ->assertCreated()->assertJsonPath('data.locale', 'ur');
        $account = User::where('email', 'synthetic-access@example.test')->firstOrFail();
        $path = "/api/users/{$account->id}";
        $account->schools()->attach($school, ['role' => 'teacher']);

        $this->patchJson($path, ['password' => 'Edit123'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->patchJson($path, ['password' => 'Edit1234'])->assertOk();

        $this->getJson('/api/admin/permissions')->assertOk()->assertJsonStructure(['data' => [['name', 'role_count']]]);
        $this->deleteJson($path)->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->patchJson($path, ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->actingAs($account->fresh(), 'sanctum')->getJson('/api/me')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/schools/{$school->id}/users/{$account->id}")->assertNoContent();
        $this->deleteJson($path)->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $account->id]);
    }

    public function test_assigned_role_must_be_unassigned_before_deletion(): void
    {
        $school = School::factory()->create();
        $account = User::factory()->create();
        $this->actingAs($this->admin(), 'sanctum');
        $rolePath = "/api/schools/{$school->id}/roles";
        $memberPath = "/api/schools/{$school->id}/users";
        $id = $this->postJson($rolePath, ['name' => 'admissions-reviewer', 'permissions' => ['inquiries.view']])
            ->assertCreated()->json('data.id');
        $this->postJson($memberPath, ['email' => $account->email, 'role' => 'admissions-reviewer'])->assertCreated();
        $this->patchJson("{$rolePath}/{$id}", ['name' => 'admissions-coordinator'])
            ->assertOk()->assertJsonPath('data.name', 'admissions-coordinator');
        $this->getJson($memberPath)->assertJsonPath('data.0.role', 'admissions-coordinator');
        $this->deleteJson("{$rolePath}/{$id}")->assertUnprocessable();
        $this->deleteJson("{$memberPath}/{$account->id}")->assertNoContent();
        $this->deleteJson("{$rolePath}/{$id}")->assertNoContent();
    }

    public function test_platform_admin_cannot_remove_a_super_admin_branch_membership(): void
    {
        $branch = School::factory()->create();
        $member = $this->admin();
        $membership = ['school_id' => $branch->id, 'user_id' => $member->id, 'role' => 'admin'];
        $member->schools()->attach($branch, ['role' => 'admin']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/schools/{$branch->id}/users/{$member->id}");

        $this->assertDatabaseHas('school_user', $membership);
        $response->assertUnprocessable()->assertJsonValidationErrors('user');
    }

    public function test_branch_admin_cannot_open_platform_user_accounts(): void
    {
        $branch = School::factory()->create();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->schools()->attach($branch, ['role' => 'admin']);

        $this->withoutVite()->actingAs($manager)->get('/admin/users')->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    }
}
