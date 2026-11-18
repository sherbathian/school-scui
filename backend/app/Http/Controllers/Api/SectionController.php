<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Section;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $sections = $schoolRecord->sections()->orderBy('title')->get();

        return response()->json(['data' => $sections->map(fn (Section $section) => $this->sectionData($section))->values()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('sections', 'title')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
        ]);
        $section = $schoolRecord->sections()->create($validated + ['status' => 'active']);

        return response()->json(['data' => $this->sectionData($section)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $school, int $section): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $sectionRecord = $schoolRecord->sections()->findOrFail($section);

        return response()->json(['data' => $this->sectionData($sectionRecord)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $school, int $section): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $sectionRecord = $schoolRecord->sections()->findOrFail($section);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('sections', 'title')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))->ignore($sectionRecord->id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
        $sectionRecord->update($validated);

        return response()->json(['data' => $this->sectionData($sectionRecord)]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $school, int $section): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $sectionRecord = $schoolRecord->sections()->findOrFail($section);
        $sectionRecord->update(['status' => 'inactive']);

        return response()->json([], 204);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin()
            ? School::query()
            : $request->user()->activeSchools();
        $schoolRecord = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $schoolRecord);

        return $schoolRecord;
    }

    /** @return array<string, mixed> */
    private function sectionData(Section $section): array
    {
        return [
            'id' => $section->id,
            'title' => $section->title,
            'status' => $section->status,
        ];
    }
}
