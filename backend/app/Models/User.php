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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'phone', 'phone_verified_at', 'last_login_at'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
                ->orWhereExists($this->activeOwnerships()->whereColumn('school_group_user.school_group_id', 'schools.group_id'));
        });
    }

    public function schoolAccessRole(School $school): ?string
    {
        if ($this->ownsSchool($school)) {
            return 'owner';
        }

        return $this->schools()->whereKey($school->id)->wherePivot('is_active', true)->first()?->pivot->role;
    }

    private function activeOwnerships(): \Illuminate\Database\Query\Builder
    {
        return DB::table('school_group_user')->join('school_groups', 'school_groups.id', '=', 'school_group_user.school_group_id')
            ->where('school_group_user.user_id', $this->id)->where('role', 'owner')
            ->where('school_group_user.is_active', true)->where('school_groups.status', 'active')
            ->whereExists(DB::table('users')->whereColumn('users.id', 'school_group_user.user_id')->where('users.is_active', true));
    }

    private function ownsSchool(School $school): bool
    {
        return $this->is_active && $school->status === 'active' && $school->group_id !== null
            && $this->activeOwnerships()->where('school_group_id', $school->group_id)->exists();
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

    public function canManageSchool(School $school): bool
    {
        return $this->hasSchoolPermission($school, 'schools.manage');
    }

    public function hasAnySchoolManagementPermission(School $school): bool
    {
        if ($this->isSuperAdmin() || $this->ownsSchool($school)) {
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
                return in_array($this->schoolAccessRole($school) ?? '', ['school_admin', 'admin'], true);
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

        if ($this->ownsSchool($school)) {
            return in_array($permission, $this->ownerPermissions(), true);
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

            return $this->legacyMembershipPermission($this->schoolAccessRole($school) ?? '', $permission);
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

        if ($this->ownsSchool($school)) {
            return $this->ownerPermissions();
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
                return $this->getAllPermissions()->pluck('name')->unique()->sort()->values()->all();
            }

            $role = $this->schoolAccessRole($school);

            return $role ? $this->legacyMembershipPermissions($role) : [];
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
