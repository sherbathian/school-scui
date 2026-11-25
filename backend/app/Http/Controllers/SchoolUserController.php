<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\BranchAuditLog;
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
    public function __construct(private readonly SchoolRoles $roles, private readonly BranchAuditLog $audit) {}

    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        Gate::authorize('manageUsers', $schoolRecord);

        return response()->json([
            'data' => $schoolRecord->users()
                ->leftJoin('students as linked_student', fn ($join) => $join->on('linked_student.id', '=', 'school_user.student_id')
                    ->on('linked_student.school_id', '=', 'school_user.school_id'))
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.email', 'linked_student.admission_number as student_admission_number'])
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

        DB::transaction(function () use ($request, $schoolRecord, $user, $validated, $role): void {
            $schoolRecord->users()->attach($user, [
                'role' => $validated['role'],
                'is_active' => $validated['is_active'] ?? true,
                'is_primary_contact' => $validated['is_primary_contact'] ?? false,
            ]);
            $user->assignRole($role);
            $this->audit->record($request->user(), $schoolRecord->id, 'membership.added', $user->id, null, $this->audit->membershipState($schoolRecord->id, $user));
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
            'student_admission_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'direct_permissions' => ['sometimes', 'array'],
            'direct_permissions.*' => ['string', Rule::in($this->roles->assignablePermissions())],
            'is_primary_contact' => ['sometimes', 'required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $member, $schoolRecord, $validated, $role): void {
            $before = $this->audit->membershipState($schoolRecord->id, $member);
            abort_if($before === null, 404);
            if (isset($validated['role'])) {
                if ($member->isSuperAdmin()) {
                    throw ValidationException::withMessages(['role' => ['The Super Admin role cannot be changed.']]);
                }
                $member->syncRoles([$role]);
            }

            if (array_key_exists('direct_permissions', $validated)) {
                $member->syncPermissions($validated['direct_permissions']);
            }

            $changes = collect($validated)->except(['direct_permissions', 'student_admission_number'])->all();
            $studentRole = ($role ?? $before['role']) === 'student';
            if (! $studentRole || array_key_exists('student_admission_number', $validated)) {
                $admission = $validated['student_admission_number'] ?? null;
                $changes['student_id'] = null;
                if ($admission !== null) {
                    $student = $schoolRecord->students()->where('admission_number', $admission)->lockForUpdate()->first();
                    if (! $studentRole || ! $student || DB::table('school_user')->where('school_id', $schoolRecord->id)
                        ->where('student_id', $student->id)->where('user_id', '!=', $member->id)->exists()) {
                        throw ValidationException::withMessages(['student_admission_number' => ['Choose an unlinked admission number in this branch for a student account.']]);
                    }
                    $changes['student_id'] = $student->id;
                }
            }
            $schoolRecord->users()->updateExistingPivot($member->id, $changes);
            $this->audit->record($request->user(), $schoolRecord->id, 'membership.updated', $member->id, $before, $this->audit->membershipState($schoolRecord->id, $member));
        });

        $member = $schoolRecord->users()->whereKey($member->id)->firstOrFail();

        return response()->json(['data' => $this->memberData($member, $member->pivot->student_id ? Student::query()->where('school_id', $school)->whereKey($member->pivot->student_id)->value('admission_number') : null)]);
    }

    public function destroy(Request $request, int $school, int $user): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school);
        $member = $schoolRecord->users()->whereKey($user)->firstOrFail();
        Gate::authorize('manageMember', [$schoolRecord, $member]);
        if ($member->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => ['The Super Admin school access cannot be changed here.']]);
        }
        DB::transaction(function () use ($request, $member, $schoolRecord): void {
            $before = $this->audit->membershipState($schoolRecord->id, $member);
            abort_if($before === null, 404);
            $member->syncRoles([]);
            $member->syncPermissions([]);
            $schoolRecord->users()->detach($member->id);
            $this->audit->record($request->user(), $schoolRecord->id, 'membership.removed', $member->id, $before, null);
        });

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function memberData(User $user, ?string $admission = null): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
            'student_admission_number' => $user->student_admission_number ?? $admission,
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
