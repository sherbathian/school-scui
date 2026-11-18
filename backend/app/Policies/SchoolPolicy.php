<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, School $school): bool
    {
        return $user->isSuperAdmin() || $user->activeSchools()->whereKey($school->id)->exists();
    }

    public function manage(User $user, School $school): bool
    {
        return $user->hasAnySchoolManagementPermission($school);
    }

    public function recordAttendance(User $user, School $school): bool
    {
        return $user->canRecordAttendance($school);
    }

    public function update(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, School $school): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageUsers(User $user, School $school): bool
    {
        return $user->hasSchoolPermission($school, 'users.manage');
    }

    public function manageMember(User $user, School $school, User $member): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasSchoolPermission($school, 'users.manage')
            && ! $member->isSuperAdmin()
            && $school->users()
                ->whereKey($member->id)
                ->exists();
    }
}
