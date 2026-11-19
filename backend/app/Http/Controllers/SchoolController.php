<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', School::class);

        $schools = $request->user()->isSuperAdmin()
            ? School::query()
            : $request->user()->activeSchools();

        return response()->json([
            'data' => $schools->with(['schoolSessions' => fn ($query) => $query->where('is_current', true)])
                ->orderBy('schools.name')->get(['schools.id', 'schools.name', 'schools.slug', 'schools.status', 'schools.group_id'])
                ->map(fn (School $school) => $this->schoolData($school, $request->user()))
                ->values(),
        ]);
    }

    public function show(Request $request, int $school): JsonResponse
    {
        $schoolQuery = $request->user()->isSuperAdmin()
            ? School::query()
            : $request->user()->activeSchools();

        $record = $schoolQuery->whereKey($school)->firstOrFail();
        Gate::authorize('view', $record);

        return response()->json(['data' => $this->schoolData($record, $request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', School::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);
        $slug = Str::slug(($validated['slug'] ?? '') ?: $validated['name']);

        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => ['Enter a name that can be used to create a school URL.']]);
        }

        validator(['slug' => $slug], ['slug' => ['required', 'max:255', 'unique:schools,slug']])->validate();

        $school = School::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json(['data' => $this->schoolData($school, $request->user())], 201);
    }

    public function update(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school);
        Gate::authorize('update', $record);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('schools', 'slug')->ignore($record->id)],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);

        $record->update($validated);

        return response()->json(['data' => $this->schoolData($record, $request->user())]);
    }

    public function destroy(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school);
        Gate::authorize('delete', $record);
        $record->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function schoolData(School $school, User $user): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'slug' => $school->slug,
            'status' => $school->status,
            'role' => $user->isSuperAdmin() ? 'super_admin' : $user->schoolAccessRole($school),
            'permissions' => $user->permissionsForSchool($school),
            'active_session' => ($session = $school->schoolSessions->first()) ? [
                'id' => $session->id,
                'title' => $session->title,
                'academic_year' => $session->starts_on->format('Y').'-'.$session->ends_on->format('Y'),
                'starts_on' => $session->starts_on->toDateString(),
                'ends_on' => $session->ends_on->toDateString(),
            ] : null,
        ];
    }

    private function accessibleSchool(Request $request, int $school): School
    {
        $schools = $request->user()->isSuperAdmin()
            ? School::query()
            : $request->user()->activeSchools();

        return $schools->whereKey($school)->firstOrFail();
    }
}
