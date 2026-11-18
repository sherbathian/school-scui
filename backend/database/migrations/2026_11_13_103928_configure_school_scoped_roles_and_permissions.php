<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $permissionGroups = [
            'schools' => ['view', 'manage'],
            'users' => ['manage'],
            'roles' => ['manage'],
            'students' => ['view', 'manage'],
            'inquiries' => ['view', 'manage'],
            'guardians' => ['view', 'manage'],
            'attendance' => ['view', 'manage'],
            'academics' => ['view', 'manage'],
            'staff' => ['view', 'manage'],
            'fees' => ['view', 'manage'],
            'expenses' => ['view', 'manage'],
            'reports' => ['view'],
        ];
        $permissionNames = [];

        foreach ($permissionGroups as $module => $actions) {
            foreach ($actions as $action) {
                $permission = "{$module}.{$action}";
                $permissionNames[] = $permission;
                DB::table('permissions')->updateOrInsert(
                    ['name' => $permission, 'guard_name' => 'web'],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        $superAdminRole = DB::table('roles')->where('name', 'platform-admin')->where('guard_name', 'web')->whereNull('team_id')->first();
        if ($superAdminRole) {
            DB::table('roles')->where('id', $superAdminRole->id)->update(['name' => 'super-admin', 'updated_at' => $now]);
            $superAdminRoleId = $superAdminRole->id;
        } else {
            $superAdminRoleId = DB::table('roles')->where('name', 'super-admin')->where('guard_name', 'web')->whereNull('team_id')->value('id');
            if (! $superAdminRoleId) {
                $superAdminRoleId = DB::table('roles')->insertGetId([
                    'team_id' => null,
                    'name' => 'super-admin',
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $standardUserRoleIds = DB::table('roles')->where('name', 'standard-user')->where('guard_name', 'web')->pluck('id');
        DB::table('model_has_roles')->whereIn('role_id', $standardUserRoleIds)->delete();
        DB::table('role_has_permissions')->whereIn('role_id', $standardUserRoleIds)->delete();
        DB::table('roles')->whereIn('id', $standardUserRoleIds)->delete();

        foreach (DB::table('permissions')->where('guard_name', 'web')->pluck('id') as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $superAdminRoleId,
            ]);
        }

        foreach (DB::table('users')->where('is_super_admin', true)->pluck('id') as $userId) {
            DB::table('model_has_roles')->updateOrInsert([
                'team_id' => 0,
                'role_id' => $superAdminRoleId,
                'model_type' => User::class,
                'model_id' => $userId,
            ]);
        }

        $schoolRolePermissions = [
            'admin' => $permissionNames,
            'teacher' => ['students.view', 'guardians.view', 'attendance.view', 'attendance.manage', 'academics.view', 'staff.view'],
            'account' => ['fees.view', 'fees.manage', 'expenses.view', 'expenses.manage', 'reports.view'],
            'student' => ['students.view', 'attendance.view', 'fees.view'],
        ];

        Schema::table('school_user', function (Blueprint $table): void {
            $table->string('role', 100)->default('teacher')->change();
        });

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            $roleIds = [];
            foreach ($schoolRolePermissions as $roleName => $permissions) {
                DB::table('roles')->updateOrInsert(
                    ['team_id' => $schoolId, 'name' => $roleName, 'guard_name' => 'web'],
                    ['updated_at' => $now, 'created_at' => $now],
                );
                $roleId = DB::table('roles')->where('team_id', $schoolId)->where('name', $roleName)->where('guard_name', 'web')->value('id');
                $roleIds[$roleName] = $roleId;

                $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id');
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_has_permissions')->updateOrInsert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }

            foreach (DB::table('school_user')->where('school_id', $schoolId)->get(['user_id', 'role']) as $member) {
                $roleName = $member->role === 'school_admin' ? 'admin' : $member->role;
                if (! isset($roleIds[$roleName])) {
                    continue;
                }

                DB::table('model_has_roles')->updateOrInsert([
                    'team_id' => $schoolId,
                    'role_id' => $roleIds[$roleName],
                    'model_type' => User::class,
                    'model_id' => $member->user_id,
                ]);
                DB::table('school_user')->where('school_id', $schoolId)->where('user_id', $member->user_id)->update(['role' => $roleName]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('School-scoped roles contain live permission assignments and cannot be safely rolled back.');
    }
};
