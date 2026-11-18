<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_update_and_archive_schools(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/schools', ['name' => 'North School'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'north-school')
            ->assertJsonPath('data.status', 'active');

        $school = School::where('slug', 'north-school')->firstOrFail();

        $this->patchJson('/api/schools/'.$school->id, [
            'name' => 'North Academy',
            'slug' => 'north-academy',
            'status' => 'suspended',
        ])->assertOk()->assertJsonPath('data.status', 'suspended');

        $this->deleteJson('/api/schools/'.$school->id)->assertNoContent();
        $this->assertSoftDeleted('schools', ['id' => $school->id]);
        $this->getJson('/api/schools/'.$school->id)->assertNotFound();
    }

    public function test_school_admin_can_manage_teacher_memberships_but_not_grant_admin_role(): void
    {
        $school = $this->createSchool('North School', 'north-school');
        $schoolAdmin = User::factory()->create();
        $teacher = User::factory()->create();
        $schoolAdmin->schools()->attach($school, ['role' => 'school_admin']);
        $this->actingAs($schoolAdmin, 'sanctum');

        $this->postJson('/api/schools/'.$school->id.'/users', [
            'email' => $teacher->email,
            'role' => 'teacher',
        ])->assertCreated()
            ->assertJsonPath('data.id', $teacher->id)
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/schools/'.$school->id.'/users', [
            'email' => $teacher->email,
            'role' => 'teacher',
        ])->assertUnprocessable();

        $this->postJson('/api/schools/'.$school->id.'/users', [
            'email' => $teacher->email,
            'role' => 'school_admin',
        ])->assertUnprocessable()->assertJsonPath(
            'errors.role.0',
            'Only a global administrator can grant school administrator access.',
        );

        $this->patchJson('/api/schools/'.$school->id.'/users/'.$teacher->id, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->deleteJson('/api/schools/'.$school->id.'/users/'.$teacher->id)->assertNoContent();
        $this->assertDatabaseMissing('school_user', ['school_id' => $school->id, 'user_id' => $teacher->id]);
    }

    public function test_global_admin_can_assign_school_admin_and_teacher_accounts(): void
    {
        $school = $this->createSchool('North School', 'north-school');
        $superAdmin = $this->superAdmin();
        $schoolAdmin = User::factory()->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/users', [
                'email' => $schoolAdmin->email,
                'role' => 'school_admin',
            ])->assertCreated()->assertJsonPath('data.role', 'school_admin');

        $this->getJson('/api/schools/'.$school->id.'/users')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', $schoolAdmin->email);
    }

    public function test_school_admin_cannot_manage_other_schools_or_school_admin_memberships(): void
    {
        $school = $this->createSchool('North School', 'north-school');
        $otherSchool = $this->createSchool('South School', 'south-school');
        $schoolAdmin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $schoolAdmin->schools()->attach($school, ['role' => 'school_admin']);
        $otherAdmin->schools()->attach($school, ['role' => 'school_admin']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->getJson('/api/schools/'.$otherSchool->id.'/users')
            ->assertNotFound();

        $this->deleteJson('/api/schools/'.$school->id.'/users/'.$otherAdmin->id)
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_create_or_archive_schools(): void
    {
        $schoolAdmin = User::factory()->create();
        $school = $this->createSchool('North School', 'north-school');
        $schoolAdmin->schools()->attach($school, ['role' => 'school_admin']);

        $this->actingAs($schoolAdmin, 'sanctum')
            ->postJson('/api/schools', ['name' => 'New School'])
            ->assertForbidden();

        $this->deleteJson('/api/schools/'.$school->id)->assertForbidden();
    }

    public function test_users_without_a_school_account_cannot_be_added(): void
    {
        $school = $this->createSchool('North School', 'north-school');
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/schools/'.$school->id.'/users', [
                'email' => 'missing@example.test',
                'role' => 'teacher',
            ])->assertUnprocessable()->assertJsonPath(
                'errors.email.0',
                'No account exists for this email. Ask an administrator to provision the account first.',
            );
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    private function createSchool(string $name, string $slug): School
    {
        return School::create(['name' => $name, 'slug' => $slug]);
    }
}
