<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SchoolRoleController extends Controller
{
    private const array SYSTEM_ROLES = ['admin', 'teacher', 'account', 'student'];

    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        abort_unless(
            $request->user()->hasSchoolPermission($schoolRecord, 'roles.manage') || $request->user()->hasSchoolPermission($schoolRecord, 'users.manage'),
            403,
        );

        $roles = Role::query()->where('guard_name', 'web')->where(function ($query) use ($school) {
            $query->where('team_id', $school)->orWhereNull('team_id');
        })->with('permissions:id,name')->orderBy('team_id')->orderBy('name')->get();

        return response()->json(['data' => [
            'roles' => $roles->map(fn (Role $role) => $this->roleData($role, $school))->values(),
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Permission $permission) => [
                    'name' => $permission->name,
                    'module' => str($permission->name)->before('.')->headline()->toString(),
                    'action' => str($permission->name)->after('.')->headline()->toString(),
                ])->values(),
        ]]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $this->authorizeRoleManagement($request, $schoolRecord);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('roles', 'name')->where(fn ($query) => $query->where('team_id', $school)->where('guard_name', 'web'))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        if (in_array($validated['name'], ['super-admin', 'super_admin', 'platform-admin', 'platform_admin'], true)) {
            throw ValidationException::withMessages(['name' => ['The Super Admin role is reserved and cannot be changed.']]);
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'team_id' => $school,
        ]);
        $role->syncPermissions($validated['permissions'] ?? []);

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
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        if (array_key_exists('name', $validated) && ! in_array($record->name, self::SYSTEM_ROLES, true)) {
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

        return response()->json(['data' => $this->roleData($record->fresh()->load('permissions:id,name'), $school)]);
    }

    public function destroy(Request $request, int $school, int $role): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $this->authorizeRoleManagement($request, $schoolRecord);
        $record = Role::query()->where('team_id', $school)->where('guard_name', 'web')->findOrFail($role);

        if (in_array($record->name, self::SYSTEM_ROLES, true)) {
            throw ValidationException::withMessages(['role' => ['Built-in roles cannot be deleted.']]);
        }

        $isAssigned = DB::table('model_has_roles')->where('team_id', $school)->where('role_id', $record->id)->exists()
            || DB::table('school_user')->where('school_id', $school)->where('role', $record->name)->exists();
        if ($isAssigned) {
            throw ValidationException::withMessages(['role' => ['Reassign all users before deleting this role.']]);
        }

        $record->delete();

        return response()->json([], 204);
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
        $isBuiltIn = $immutable || in_array($role->name, self::SYSTEM_ROLES, true);

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
