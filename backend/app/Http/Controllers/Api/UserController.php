<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function permissionCatalogue(): JsonResponse
    {
        return response()->json(['data' => Permission::query()->where('guard_name', 'web')->orderBy('name')
            ->select(['id', 'name'])->withCount('roles')->get()->map(fn (Permission $permission) => [
                'id' => $permission->id, 'name' => $permission->name,
                'role_count' => $permission->roles_count,
            ])]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.manage');
        $search = trim((string) $request->query('search', ''));
        $users = User::query()
            ->with(['roles:id,name', 'schools:id,name'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')->get();

        return response()->json(['data' => $users->map(fn (User $user) => $this->userData($user))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.manage');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:72'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['sometimes', Rule::in(['en', 'ur'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validatePasswordBytes($validated['password']);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'is_super_admin' => false,
                ...collect($validated)->only(['phone', 'locale'])->all(),
                'is_active' => $validated['is_active'] ?? true,
            ])->save();

            return $user;
        });

        return response()->json(['data' => $this->userData($user->load(['roles:id,name', 'schools:id,name']))], 201);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $this->authorizePermission($request, 'users.manage');
        $record = User::query()->findOrFail($user);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:12', 'max:72'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['sometimes', Rule::in(['en', 'ur'])],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        abort_if($record->isSuperAdmin() && ! $record->is($request->user()), 403);
        $this->validatePasswordBytes($validated['password'] ?? null);

        if ($record->isSuperAdmin() && array_key_exists('is_active', $validated) && ! $validated['is_active']) {
            throw ValidationException::withMessages(['is_active' => ['The Super Admin account cannot be deactivated from the user module.']]);
        }

        DB::transaction(function () use ($validated, $record): void {
            $changes = collect($validated)->except(['password', 'is_active'])->all();
            if (isset($validated['password']) && $validated['password'] !== '') {
                $changes['password'] = $validated['password'];
            }
            if (array_key_exists('is_active', $validated)) {
                $changes['is_active'] = $validated['is_active'];
            }
            if (array_key_exists('phone', $changes) && $changes['phone'] !== $record->phone) {
                $changes['phone_verified_at'] = null;
            }
            $record->forceFill($changes)->save();
        });

        return response()->json(['data' => $this->userData($record->fresh()->load(['roles:id,name', 'schools:id,name']))]);
    }

    public function destroy(Request $request, int $user): JsonResponse
    {
        $this->authorizePermission($request, 'users.manage');
        $record = User::query()->findOrFail($user);

        if ($record->is($request->user())) {
            throw ValidationException::withMessages(['user' => ['You cannot delete the account you are currently using.']]);
        }
        if ($record->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => ['The Super Admin account and its role are immutable.']]);
        }
        if ($record->schools()->exists() || $record->staffProfile()->exists()) {
            throw ValidationException::withMessages(['user' => ['Remove this user from their schools and staff profile before deleting the account. You can deactivate it instead.']]);
        }
        if (DB::table('school_group_user')->where('user_id', $record->id)->exists()
            || DB::table('listing_claims')->where('user_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['user' => ['Deactivate this account to preserve its ownership and claim history.']]);
        }

        $record->delete();

        return response()->json([], 204);
    }

    private function validatePasswordBytes(?string $password): void
    {
        if ($password !== null && strlen($password) > 72) {
            throw ValidationException::withMessages(['password' => ['Use a password no longer than 72 bytes.']]);
        }
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        $actor = $request->user();
        abort_unless($actor->is_active && $actor->isSuperAdmin(), 403);
        if ($actor->hasRole('super-admin')) {
            abort_unless($actor->hasPermissionTo($permission), 403);
        }
    }

    /** @return array<string, mixed> */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'global_role' => $user->isSuperAdmin() ? 'super-admin' : 'user',
            'is_super_admin' => $user->isSuperAdmin(),
            'is_active' => (bool) $user->is_active,
            'schools' => $user->schools->map(fn (School $school) => [
                'id' => $school->id,
                'name' => $school->name,
                'role' => $school->pivot->role,
                'is_active' => (bool) $school->pivot->is_active,
            ])->values(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
