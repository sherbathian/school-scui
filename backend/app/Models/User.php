<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
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
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function activeSchools(): BelongsToMany
    {
        return $this->schools()->wherePivot('is_active', true)->where('schools.status', 'active');
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
        if ($this->isSuperAdmin()) {
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
                return in_array($membership->pivot->role, ['school_admin', 'admin'], true);
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

            return $this->legacyMembershipPermission($membership->pivot->role, $permission);
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

            $role = $this->activeSchools()->whereKey($school->getKey())->first()?->pivot->role;

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
