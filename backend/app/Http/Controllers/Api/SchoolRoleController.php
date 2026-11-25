<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\BranchAuditLog;
use App\Services\SchoolRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SchoolRoleController extends Controller
{
    public function __construct(private readonly SchoolRoles $roles, private readonly BranchAuditLog $audit) {}

    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        abort_unless(
            $request->user()->hasSchoolPermission($schoolRecord, 'roles.manage') || $request->user()->hasSchoolPermission($schoolRecord, 'users.manage'),
            403,
        );

        $this->roles->ensureDefaults($schoolRecord);

        $roles = Role::query()->where('guard_name', 'web')->where(function ($query) use ($school) {
            $query->where('team_id', $school)->orWhereNull('team_id');
        })->with('permissions:id,name')->orderBy('team_id')->orderBy('name')->get();

        return response()->json(['data' => [
            'roles' => $roles->map(fn (Role $role) => $this->roleData($role, $school))->values(),
            'permissions' => Permission::query()->where('guard_name', 'web')->whereIn('name', $this->roles->assignablePermissions())->orderBy('name')->get(['id', 'name'])
                ->map(fn (Permission $permission) => [
                    'name' => $permission->name,
                    'module' => str($permission->name)->before('.')->headline()->toString(),
                    'action' => str($permission->name)->after('.')->headline()->toString(),
                ])->values(),
        ]]);
    }

    public function assignedUsers(Request $request, int $school, int $role): JsonResponse
    {
        $branch = $this->accessibleSchool($request, $school);
        $record = Role::query()->where('guard_name', 'web')->where(function ($query) use ($request, $school): void {
            $query->where('team_id', $school);
            if ($request->user()->isSuperAdmin()) {
                $query->orWhereNull('team_id');
            }
        })->findOrFail($role);

        $users = DB::table('model_has_roles as assignment')
            ->join('users', 'users.id', '=', 'assignment.model_id')
            ->where('assignment.team_id', $record->team_id ?? 0)
            ->where('assignment.role_id', $record->id)
            ->where('assignment.model_type', (new User)->getMorphClass());
        if ($record->team_id !== null) {
            $users->join('school_user as membership', 'membership.user_id', '=', 'users.id')
                ->where('membership.school_id', $branch->id)
                ->addSelect('membership.is_active as membership_active');
        }

        return response()->json(['data' => $users->addSelect('users.id', 'users.name', 'users.email', 'users.is_active')
            ->orderBy('users.name')->orderBy('users.id')->get()
            ->map(fn (object $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active && (bool) ($user->membership_active ?? true),
            ])]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $this->authorizeRoleManagement($request, $schoolRecord);
        $this->roles->ensureDefaults($schoolRecord);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('roles', 'name')->where(fn ($query) => $query->where('team_id', $school)->where('guard_name', 'web'))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in($this->roles->assignablePermissions())],
        ]);

        $this->authorizeDelegation($request, $schoolRecord, $validated['permissions'] ?? []);

        if (in_array(strtolower($validated['name']), ['super-admin', 'super_admin', 'platform-admin', 'platform_admin'], true)) {
            throw ValidationException::withMessages(['name' => ['The Super Admin role is reserved and cannot be changed.']]);
        }

        $role = DB::transaction(function () use ($request, $school, $validated): Role {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'team_id' => $school,
            ]);
            $role->syncPermissions($validated['permissions'] ?? []);

            $this->audit->record($request->user(), $school, 'role.created', $role->id, null, $this->audit->roleState($role));

            return $role;
        });

        return response()->json(['data' => $this->roleData($role->load('permissions:id,name'), $school)], 201);
    }

    public function update(Request $request, int $school, int $role): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $this->authorizeRoleManagement($request, $schoolRecord);
        $record = Role::query()->where('team_id', $school)->where('guard_name', 'web')->findOrFail($role);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:64', 'alpha_dash', Rule::unique('roles', 'name')->where(fn ($query) => $query->where('team_id', $school)->where('guard_name', 'web'))->ignore($record->id)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in($this->roles->assignablePermissions())],
        ]);

        if (isset($validated['name']) && in_array(strtolower($validated['name']), ['super-admin', 'super_admin', 'platform-admin', 'platform_admin'], true)) {
            throw ValidationException::withMessages(['name' => ['The Super Admin role is reserved.']]);
        }

        $this->authorizeDelegation($request, $schoolRecord, $record->permissions->pluck('name')->all());
        $this->authorizeDelegation($request, $schoolRecord, $validated['permissions'] ?? []);

        DB::transaction(function () use ($request, $validated, $record, $school, $schoolRecord): void {
            $record = Role::query()->lockForUpdate()->findOrFail($record->id);
            $before = $this->audit->roleState($record);
            $this->authorizeDelegation($request, $schoolRecord, $before['permissions']);
            if (array_key_exists('name', $validated) && ! in_array($record->name, SchoolRoles::BUILT_IN, true)) {
                $oldName = $record->name;
                $record->name = $validated['name'];
                $record->save();
                DB::table('school_user')->where('school_id', $school)->where('role', $oldName)->update(['role' => $record->name]);
            } elseif (array_key_exists('name', $validated) && $validated['name'] !== $record->name) {
                throw ValidationException::withMessages(['name' => ['System role names cannot be changed.']]);
            }

            if (array_key_exists('permissions', $validated)) {
                $record->syncPermissions($validated['permissions']);
            }
            $this->audit->record($request->user(), $school, 'role.updated', $record->id, $before, $this->audit->roleState($record));
        });

        return response()->json(['data' => $this->roleData($record->fresh()->load('permissions:id,name'), $school)]);
    }

    public function destroy(Request $request, int $school, int $role): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $this->authorizeRoleManagement($request, $schoolRecord);
        $record = Role::query()->where('team_id', $school)->where('guard_name', 'web')->findOrFail($role);

        DB::transaction(function () use ($request, $school, $record): void {
            $record = Role::query()->lockForUpdate()->findOrFail($record->id);
            if (in_array($record->name, SchoolRoles::BUILT_IN, true)) {
                throw ValidationException::withMessages(['role' => ['Built-in roles cannot be deleted.']]);
            }

            $isAssigned = DB::table('model_has_roles')->where('team_id', $school)->where('role_id', $record->id)->exists()
                || DB::table('school_user')->where('school_id', $school)->where('role', $record->name)->exists();
            if ($isAssigned) {
                throw ValidationException::withMessages(['role' => ['Reassign all users before deleting this role.']]);
            }

            $before = $this->audit->roleState($record);
            $record->delete();
            $this->audit->record($request->user(), $school, 'role.deleted', $record->id, $before, null);
        });

        return response()->json([], 204);
    }

    private function authorizeDelegation(Request $request, School $school, array $permissions): void
    {
        foreach ($permissions as $permission) {
            abort_unless($request->user()->hasSchoolPermission($school, $permission), 403);
        }
    }

    private function authorizeRoleManagement(Request $request, School $school): void
    {
        abort_unless($request->user()->hasSchoolPermission($school, 'roles.manage'), 403);
    }

    private function accessibleSchool(Request $request, int $school): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();

        return $schools->whereKey($school)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function roleData(Role $role, int $school): array
    {
        $immutable = $role->name === 'super-admin' && $role->team_id === null;
        $isBuiltIn = $immutable || in_array($role->name, SchoolRoles::BUILT_IN, true);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'team_id' => $role->team_id,
            'is_built_in' => $isBuiltIn,
            'is_immutable' => $immutable,
            'permissions' => $immutable
                ? Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->values()
                : $role->permissions->pluck('name')->values(),
            'assigned_users' => DB::table('model_has_roles')->where('team_id', $role->team_id ?? 0)->where('role_id', $role->id)->count(),
        ];
    }
}
