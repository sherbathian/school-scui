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
                'is_active' => true,
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

        $this->roles->ensureDefaults($schoolRecord);
        $role = $request->has('role') ? $this->requestedRole($request, $schoolRecord) : null;
        $validated = $request->validate([
            'role' => ['sometimes', 'required', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        DB::transaction(function () use ($member, $schoolRecord, $validated, $role): void {
            if (isset($validated['role'])) {
                if ($member->isSuperAdmin()) {
                    throw ValidationException::withMessages(['role' => ['The Super Admin role cannot be changed.']]);
                }
                $member->syncRoles([$role]);
            }

            $schoolRecord->users()->updateExistingPivot($member->id, $validated);
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
            'is_super_admin' => $user->isSuperAdmin(),
            'is_active' => (bool) $user->pivot->is_active,
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

        return $role;
    }

    private function accessibleSchool(Request $request, int $school): School
    {
        $schools = $request->user()->isSuperAdmin()
            ? School::query()
            : $request->user()->activeSchools();

        return $schools->whereKey($school)->firstOrFail();
    }
}
