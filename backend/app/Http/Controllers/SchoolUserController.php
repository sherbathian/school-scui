<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use App\Services\SchoolRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SchoolUserController extends Controller
{
    public function __construct(private readonly SchoolRoles $roles) {}

    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        Gate::authorize('manageUsers', $schoolRecord);

        return response()->json([
            'data' => $schoolRecord->users()
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $user) => $this->memberData($user))
                ->values(),
        ]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        Gate::authorize('manageUsers', $schoolRecord);

        $this->roles->ensureDefaults($schoolRecord);
        $role = $this->requestedRole($request, $schoolRecord);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'is_primary_contact' => ['sometimes', 'required', 'boolean'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No account exists for this email. Ask an administrator to provision the account first.'],
            ]);
        }

        if ($schoolRecord->users()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages(['email' => ['This user already belongs to the school.']]);
        }

        DB::transaction(function () use ($schoolRecord, $user, $validated, $role): void {
            $schoolRecord->users()->attach($user, [
                'role' => $validated['role'],
                'is_active' => $validated['is_active'] ?? true,
                'is_primary_contact' => $validated['is_primary_contact'] ?? false,
            ]);
            $user->assignRole($role);
        });

        $member = $schoolRecord->users()->whereKey($user->id)->firstOrFail();

        return response()->json(['data' => $this->memberData($member)], 201);
    }

    public function update(Request $request, int $school, int $user): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $member = $schoolRecord->users()->whereKey($user)->firstOrFail();
        Gate::authorize('manageMember', [$schoolRecord, $member]);

        if ($request->has('direct_permissions')) {
            abort_unless($request->user()->isSuperAdmin() && ! $member->isSuperAdmin(), 403);
        }

        $this->roles->ensureDefaults($schoolRecord);
        $role = $request->has('role') ? $this->requestedRole($request, $schoolRecord) : null;
        $validated = $request->validate([
            'role' => ['sometimes', 'required', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'direct_permissions' => ['sometimes', 'array'],
            'direct_permissions.*' => ['string', Rule::in($this->roles->assignablePermissions())],
            'is_primary_contact' => ['sometimes', 'required', 'boolean'],
        ]);

        DB::transaction(function () use ($member, $schoolRecord, $validated, $role): void {
            if (isset($validated['role'])) {
                if ($member->isSuperAdmin()) {
                    throw ValidationException::withMessages(['role' => ['The Super Admin role cannot be changed.']]);
                }
                $member->syncRoles([$role]);
            }

            if (array_key_exists('direct_permissions', $validated)) {
                $member->syncPermissions($validated['direct_permissions']);
            }

            $schoolRecord->users()->updateExistingPivot($member->id, collect($validated)->except('direct_permissions')->all());
        });

        $member = $schoolRecord->users()->whereKey($member->id)->firstOrFail();

        return response()->json(['data' => $this->memberData($member)]);
    }

    public function destroy(Request $request, int $school, int $user): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $member = $schoolRecord->users()->whereKey($user)->firstOrFail();
        Gate::authorize('manageMember', [$schoolRecord, $member]);
        if ($member->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => ['The Super Admin school access cannot be changed here.']]);
        }
        DB::transaction(function () use ($member, $schoolRecord): void {
            $member->syncRoles([]);
            $member->syncPermissions([]);
            $schoolRecord->users()->detach($member->id);
        });

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function memberData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')->sort()->values()->all(),
            'is_super_admin' => $user->isSuperAdmin(),
            'is_active' => (bool) $user->pivot->is_active,
            'is_primary_contact' => (bool) $user->pivot->is_primary_contact,
        ];
    }

    private function requestedRole(Request $request, School $school): string
    {
        $input = $request->validate(['role' => ['required', 'string']])['role'];
        $role = strtolower($input) === 'school_admin' ? 'admin' : $input;

        if (strtolower($role) === 'admin' && ! $request->user()->isSuperAdmin()) {
            throw ValidationException::withMessages(['role' => ['Only a global administrator can grant school administrator access.']]);
        }

        validator(['role' => $role], [
            'role' => [Rule::exists('roles', 'name')->where('team_id', $school->id)->where('guard_name', 'web')],
        ])->validate();

        $assignedRole = Role::query()->where('team_id', $school->id)->where('guard_name', 'web')
            ->where('name', $role)->with('permissions:id,name')->firstOrFail();
        foreach ($assignedRole->permissions as $permission) {
            abort_unless($request->user()->hasSchoolPermission($school, $permission->name), 403);
        }

        return $assignedRole->name;
    }

    private function accessibleSchool(Request $request, int $school): School
    {
        $schools = $request->user()->isSuperAdmin()
            ? School::query()
            : $request->user()->activeSchools();

        return $schools->whereKey($school)->firstOrFail();
    }
}
