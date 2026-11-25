<?php

namespace App\Services;

use App\Models\BranchAuditEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class BranchAuditLog
{
    public const array ACTIONS = [
        'membership.added', 'membership.updated', 'membership.removed',
        'role.created', 'role.updated', 'role.deleted',
        'module.enabled', 'module.disabled', 'module.reset',
    ];

    public function record(User $actor, int $school, string $action, int|string $target, ?array $before, ?array $after): void
    {
        if ($before !== $after) {
            BranchAuditEntry::create([
                'actor_id' => $actor->id, 'school_id' => $school, 'action' => $action,
                'target_id' => (string) $target, 'before' => $before, 'after' => $after, 'recorded_at' => now(),
            ]);
        }
    }

    public function roleState(Role $role): array
    {
        return ['name' => $role->name, 'permissions' => $role->permissions()->orderBy('name')->pluck('name')->all()];
    }

    public function membershipState(int $school, User $member): ?array
    {
        $pivot = DB::table('school_user')->where('school_id', $school)->where('user_id', $member->id)->lockForUpdate()->first();
        if (! $pivot) {
            return null;
        }

        return [
            'role' => $pivot->role,
            'active' => (bool) $pivot->is_active,
            'primary_contact' => (bool) $pivot->is_primary_contact,
            'permissions' => $member->permissions()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
