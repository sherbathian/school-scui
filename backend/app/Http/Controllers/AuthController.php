<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        return $this->authenticate($request, false);
    }

    public function adminLogin(Request $request): JsonResponse
    {
        return $this->authenticate($request, true);
    }

    private function authenticate(Request $request, bool $adminOnly): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $remember = $credentials['remember'] ?? false;
        unset($credentials['remember']);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active
            || ! Hash::check($credentials['password'], $user->password)
            || ($adminOnly && Gate::forUser($user)->denies('moderateListings', School::class))
            || ! Auth::guard('web')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->getRoleNames()->values(),
            'schools' => $user->activeSchools()
                ->orderBy('schools.name')
                ->get(['schools.id', 'schools.name', 'schools.slug', 'schools.status', 'schools.group_id'])
                ->map(fn ($school) => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                    'role' => $user->schoolAccessRole($school),
                ])
                ->values(),
        ];
    }
}
