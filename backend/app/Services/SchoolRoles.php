<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SchoolRoles
{
    public const array BUILT_IN = ['admin', 'teacher', 'account', 'student'];

    public function assignablePermissions(): array
    {
        return Permission::query()->where('guard_name', 'web')
            ->whereNotIn('name', ['users.view', 'users.create', 'users.update', 'users.delete'])
            ->where('name', 'not like', 'platform.%')->orderBy('name')->pluck('name')->all();
    }

    public function ensureDefaults(School $school): void
    {
        $attributes = ['team_id' => $school->id, 'guard_name' => 'web'];
        $existing = Role::query()->where($attributes)->pluck('name')->all();

        foreach (array_diff(self::BUILT_IN, $existing) as $name) {
            DB::transaction(function () use ($attributes, $name): void {
                $role = Role::query()->firstOrCreate([...$attributes, 'name' => $name]);

                if (! $role->wasRecentlyCreated) {
                    return;
                }

                $permissions = match ($name) {
                    'admin' => array_values(array_diff($this->assignablePermissions(), ['audit.view', 'fees.receipt.post', 'fees.receipt.reverse', 'fees.period.manage', 'fees.audit.view'])),
                    'teacher' => ['students.view', 'guardians.view', 'attendance.view', 'attendance.manage', 'academics.view', 'staff.view'],
                    'account' => ['fees.view', 'fees.manage', 'expenses.view', 'expenses.manage', 'reports.view'],
                    'student' => ['students.view', 'attendance.view', 'fees.view'],
                };
                $role->syncPermissions($permissions);
            }, 3);
        }
    }
}
