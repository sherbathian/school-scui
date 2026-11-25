<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_changes_record_one_entry_and_no_ops_record_none(): void
    {
        $actor = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $branch = School::factory()->create();
        $roles = "/api/schools/{$branch->id}/roles";
        $this->actingAs($actor, 'web');
        $role = $this->postJson($roles, ['name' => 'auditor'])->assertCreated()->json('data.id');
        $this->patchJson("$roles/$role", ['name' => 'auditor'])->assertOk();
        $this->patchJson("$roles/$role", ['name' => 'reviewer'])->assertOk();
        $this->deleteJson("$roles/$role")->assertNoContent();

        $this->assertDatabaseCount('branch_audit_entries', 3);
        $this->assertDatabaseHas('branch_audit_entries', [
            'actor_id' => $actor->id, 'school_id' => $branch->id,
            'action' => 'role.updated', 'target_id' => (string) $role,
        ]);
    }

    public function test_view_requires_an_explicit_permission_and_filters_cannot_widen_scope(): void
    {
        $actor = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);
        $allowed = School::factory()->create(['status' => 'active']);
        $other = School::factory()->create(['status' => 'active']);
        $viewer->schools()->attach($allowed, ['role' => 'teacher', 'is_active' => true]);
        $viewer->schools()->attach($other, ['role' => 'teacher', 'is_active' => true]);
        $this->get('/admin/audit-log')->assertRedirect();
        $this->actingAs($viewer)->get('/admin/audit-log')->assertForbidden();
        $this->actingAs($actor, 'web');
        foreach ([$allowed, $other] as $branch) {
            $this->postJson("/api/schools/{$branch->id}/roles", ['name' => 'reviewer'])->assertCreated();
        }
        setPermissionsTeamId($allowed->id);
        $viewer->givePermissionTo('audit.view');
        setPermissionsTeamId(null);

        $this->actingAs($viewer)->get('/admin/audit-log')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/AuditLog')
                ->has('entries.data', 1)->where('entries.data.0.school_id', $allowed->id)
                ->has('branches', 1));
        $this->get("/admin/audit-log?branch={$other->id}")->assertForbidden();
        $this->get('/admin/audit-log?action=module.reset')->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries.data', 0));
        $this->actingAs($actor, 'web')->get('/admin/audit-log')->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries.data', 2));
    }

    public function test_audit_write_failure_rolls_back_the_role_creation(): void
    {
        $actor = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $branch = School::factory()->create();
        $this->partialMock(\App\Services\BranchAuditLog::class)->shouldReceive('record')
            ->once()->andThrow(new \RuntimeException('Synthetic audit failure'));

        $this->actingAs($actor, 'web')->postJson("/api/schools/{$branch->id}/roles", ['name' => 'reviewer'])->assertServerError();
        $this->assertDatabaseMissing('roles', ['team_id' => $branch->id, 'name' => 'reviewer']);
    }

    public function test_membership_and_module_changes_are_audited_without_personal_data(): void
    {
        $actor = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $member = User::factory()->create();
        $branch = School::factory()->create(['status' => 'active']);
        $this->actingAs($actor, 'web');

        $memberships = "/api/schools/{$branch->id}/users";
        $this->postJson($memberships, ['email' => $member->email, 'role' => 'teacher'])->assertCreated();
        $membership = "$memberships/{$member->id}";
        $this->patchJson($membership, ['role' => 'admin'])->assertOk();
        $this->patchJson($membership, ['role' => 'admin'])->assertOk();
        $this->deleteJson($membership)->assertNoContent();
        $this->deleteJson($membership)->assertNotFound();

        $module = "/api/admin/branches/{$branch->id}/modules/attendance";
        $this->patchJson($module, ['enabled' => false])->assertOk();
        $this->patchJson($module, ['enabled' => false])->assertOk();
        $this->deleteJson($module)->assertOk();
        $this->deleteJson($module)->assertOk();

        $entries = \App\Models\BranchAuditEntry::query()->orderBy('id')->get();
        $this->assertSame([
            'membership.added', 'membership.updated', 'membership.removed', 'module.disabled', 'module.reset',
        ], $entries->pluck('action')->all());
        $this->assertSame('teacher', $entries[1]->before['role']);
        $this->assertSame('admin', $entries[1]->after['role']);
        $this->assertStringNotContainsString($member->email, $entries->toJson());
    }
}
