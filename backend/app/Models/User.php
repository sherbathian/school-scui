<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'phone', 'phone_verified_at', 'last_login_at'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->withPivot(['role', 'is_active', 'is_primary_contact'])
            ->withTimestamps();
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function activeSchools(): Builder
    {
        return School::query()->where('schools.status', 'active')->where(function (Builder $query): void {
            $query->whereNull('schools.group_id')->orWhereExists(
                DB::table('school_groups')->whereColumn('school_groups.id', 'schools.group_id')->where('status', 'active')
            );
        })->where(function (Builder $query): void {
            $query->whereExists(DB::table('school_user')->whereColumn('school_user.school_id', 'schools.id')
                ->where('user_id', $this->id)->where('is_active', true))
                ->orWhereExists($this->activeOrganisationMemberships()->whereColumn('school_group_user.school_group_id', 'schools.group_id'));
        });
    }

    public function ownedActiveSchools(): Builder
    {
        return School::query()->where('schools.status', 'active')->whereExists(
            $this->activeOrganisationMemberships()->where('school_group_user.role', 'owner')
                ->whereColumn('school_group_user.school_group_id', 'schools.group_id')
        );
    }

    public function schoolAccessRole(School $school): ?string
    {
        $role = $this->organisationRole($school);

        return $role === 'owner' ? $role : ($this->branchMembershipRole($school) ?? $role);
    }

    private function branchMembershipRole(School $school): ?string
    {
        return $this->schools()->whereKey($school->id)->wherePivot('is_active', true)->first()?->pivot->role;
    }

    private function activeOrganisationMemberships(): \Illuminate\Database\Query\Builder
    {
        return DB::table('school_group_user')->join('school_groups', 'school_groups.id', '=', 'school_group_user.school_group_id')
            ->where('school_group_user.user_id', $this->id)
            ->whereIn('school_group_user.role', ['owner', 'group_admin', 'group_accountant', 'group_viewer'])
            ->where('school_group_user.is_active', true)->where('school_groups.status', 'active')
            ->whereExists(DB::table('users')->whereColumn('users.id', 'school_group_user.user_id')->where('users.is_active', true));
    }

    private function organisationRole(School $school): ?string
    {
        if (! $this->is_active || $school->status !== 'active' || $school->group_id === null) {
            return null;
        }

        return $this->activeOrganisationMemberships()->where('school_group_id', $school->group_id)->value('school_group_user.role');
    }

    private function organisationPermissions(?string $role): array
    {
        return match ($role) {
            'owner', 'group_admin' => $this->ownerPermissions(),
            'group_accountant' => ['schools.view', 'fees.view', 'fees.manage', 'expenses.view', 'expenses.manage'],
            'group_viewer' => ['schools.view', 'reports.view'],
            default => [],
        };
    }

    private function ownerPermissions(): array
    {
        return Permission::query()->where('guard_name', 'web')
            ->whereNotIn('name', ['users.view', 'users.create', 'users.update', 'users.delete'])
            ->where('name', 'not like', 'platform.%')->pluck('name')->all();
    }

    public function isSuperAdmin(): bool
    {
        if ((bool) $this->is_super_admin) {
            return true;
        }

        $registrar = app(PermissionRegistrar::class);
        $teamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(0);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $this->hasRole('super-admin');
        } finally {
            $registrar->setPermissionsTeamId($teamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function canEnterSchoolhouse(): bool
    {
        return $this->is_active && ($this->isSuperAdmin() || $this->activeSchools()->exists());
    }

    /** @return list<string> */
    public function schoolhouseOperationModules(School $school): array
    {
        $modules = match ($this->schoolAccessRole($school)) {
            'admin', 'school_admin', 'owner', 'group_admin' => ['academics', 'students', 'attendance', 'guardians', 'inquiries', 'staff', 'fees', 'expenses'],
            'teacher' => ['attendance'],
            'account', 'accountant', 'group_accountant' => ['fees', 'expenses'],
            default => [],
        };
        $permissions = $this->permissionsForSchool($school);

        return array_values(array_filter($modules, fn (string $module) => in_array(
            $module.'.manage',
            $permissions,
            true,
        )));
    }

    public function canManageSchool(School $school): bool
    {
        return $this->hasSchoolPermission($school, 'schools.manage');
    }

    public function hasAnySchoolManagementPermission(School $school): bool
    {
        if ($this->isSuperAdmin() || collect($this->organisationPermissions($this->organisationRole($school)))->contains(fn (string $name) => str_ends_with($name, '.manage'))) {
            return true;
        }

        $membership = $this->activeSchools()->whereKey($school->getKey())->first();
        if (! $membership) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $teamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($school->getKey());
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            if (! $this->roles()->exists()) {
                return in_array($this->schoolAccessRole($school) ?? '', ['school_admin', 'admin'], true)
                    || $this->getDirectPermissions()->contains(fn (Permission $permission) => str_ends_with($permission->name, '.manage'));
            }

            return $this->getAllPermissions()->contains(fn (Permission $permission) => str_ends_with($permission->name, '.manage'));
        } finally {
            $registrar->setPermissionsTeamId($teamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function canRecordAttendance(School $school): bool
    {
        return $this->hasSchoolPermission($school, 'attendance.manage');
    }

    public function hasSchoolPermission(School $school, string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $role = $this->organisationRole($school);
        if (in_array($permission, $this->organisationPermissions($role), true)) {
            return true;
        }
        if ($role === 'owner') {
            return false;
        }

        $membership = $this->activeSchools()->whereKey($school->getKey())->first();
        if (! $membership) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $teamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($school->getKey());
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            if ($this->roles()->exists()) {
                return $this->hasPermissionTo($permission);
            }

            return $this->hasDirectPermission($permission) || (($role = $this->branchMembershipRole($school)) !== null && $this->legacyMembershipPermission($role, $permission));
        } finally {
            $registrar->setPermissionsTeamId($teamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /** @return list<string> */
    public function permissionsForSchool(School $school): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
        }

        $role = $this->organisationRole($school);
        $organisationPermissions = $this->organisationPermissions($role);
        if ($role === 'owner') {
            return $organisationPermissions;
        }

        if (! $this->activeSchools()->whereKey($school->getKey())->exists()) {
            return [];
        }

        $registrar = app(PermissionRegistrar::class);
        $teamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($school->getKey());
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            if ($this->roles()->exists()) {
                return $this->getAllPermissions()->pluck('name')->merge($organisationPermissions)->unique()->sort()->values()->all();
            }

            $role = $this->branchMembershipRole($school);

            return $this->getDirectPermissions()->pluck('name')
                ->merge($organisationPermissions)
                ->merge($role ? $this->legacyMembershipPermissions($role) : [])
                ->unique()->sort()->values()->all();
        } finally {
            $registrar->setPermissionsTeamId($teamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    private function legacyMembershipPermission(string $role, string $permission): bool
    {
        return in_array($permission, $this->legacyMembershipPermissions($role), true);
    }

    /** @return list<string> */
    private function legacyMembershipPermissions(string $role): array
    {
        if ($role === 'school_admin' || $role === 'admin') {
            return Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        }

        return [
            'students.view', 'guardians.view', 'attendance.view', 'attendance.manage', 'academics.view', 'staff.view',
        ];
    }
}
