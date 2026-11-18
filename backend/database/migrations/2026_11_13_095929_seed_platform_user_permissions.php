<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $permissions = ['users.view', 'users.create', 'users.update', 'users.delete'];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['name' => 'platform-admin', 'guard_name' => 'web'],
            ['updated_at' => $now, 'created_at' => $now],
        );
        DB::table('roles')->updateOrInsert(
            ['name' => 'standard-user', 'guard_name' => 'web'],
            ['updated_at' => $now, 'created_at' => $now],
        );

        $roleId = DB::table('roles')->where('name', 'platform-admin')->where('guard_name', 'web')->value('id');
        $platformAdminPermissions = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id');

        foreach ($platformAdminPermissions as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $userIds = DB::table('users')->where('is_super_admin', true)->pluck('id');

        foreach ($userIds as $userId) {
            DB::table('model_has_roles')->updateOrInsert([
                'team_id' => 0,
                'role_id' => $roleId,
                'model_type' => User::class,
                'model_id' => $userId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', ['users.view', 'users.create', 'users.update', 'users.delete'])->where('guard_name', 'web')->pluck('id');
        $roleId = DB::table('roles')->where('name', 'platform-admin')->where('guard_name', 'web')->value('id');

        if ($roleId) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        DB::table('roles')->where('name', 'standard-user')->where('guard_name', 'web')->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
