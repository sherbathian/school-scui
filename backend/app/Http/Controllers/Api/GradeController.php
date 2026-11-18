<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\School;
use App\Models\Section;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GradeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $grades = $schoolRecord->grades()
            ->with('sections:id,title,status')
            ->orderBy('title')
            ->get();

        return response()->json(['data' => $grades->map(fn (Grade $grade) => $this->gradeData($grade))->values()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('grades', 'title')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'section_ids' => ['sometimes', 'array', 'max:100'],
            'section_ids.*' => ['integer', 'distinct'],
        ]);
        $sectionIds = $this->validatedSectionIds($validated['section_ids'] ?? [], $schoolRecord);

        $grade = DB::transaction(function () use ($schoolRecord, $validated, $sectionIds): Grade {
            $grade = $schoolRecord->grades()->create([
                'title' => $validated['title'],
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($sectionIds !== []) {
                $grade->sections()->syncWithPivotValues($sectionIds, ['school_id' => $schoolRecord->id]);
            }

            return $grade->load('sections:id,title,status');
        });

        return response()->json(['data' => $this->gradeData($grade)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $school, int $grade): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $gradeRecord = $schoolRecord->grades()->with('sections:id,title,status')->findOrFail($grade);

        return response()->json(['data' => $this->gradeData($gradeRecord)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $school, int $grade): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $gradeRecord = $schoolRecord->grades()->findOrFail($grade);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('grades', 'title')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))->ignore($gradeRecord->id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'section_ids' => ['sometimes', 'array', 'max:100'],
            'section_ids.*' => ['integer', 'distinct'],
        ]);
        $sectionIds = array_key_exists('section_ids', $validated)
            ? $this->validatedSectionIds($validated['section_ids'], $schoolRecord)
            : null;

        $gradeRecord = DB::transaction(function () use ($gradeRecord, $validated, $sectionIds, $schoolRecord): Grade {
            $gradeRecord->update(collect($validated)->except('section_ids')->all());

            if ($sectionIds !== null) {
                $gradeRecord->sections()->syncWithPivotValues($sectionIds, ['school_id' => $schoolRecord->id]);
            }

            return $gradeRecord->load('sections:id,title,status');
        });

        return response()->json(['data' => $this->gradeData($gradeRecord)]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $school, int $grade): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $gradeRecord = $schoolRecord->grades()->findOrFail($grade);
        $gradeRecord->update(['status' => 'inactive']);

        return response()->json([], 204);
    }

    public function syncSections(Request $request, int $school, int $grade): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $gradeRecord = $schoolRecord->grades()->findOrFail($grade);
        $validated = $request->validate([
            'section_ids' => ['required', 'array', 'max:100'],
            'section_ids.*' => ['integer', 'distinct'],
        ]);
        $sectionIds = $this->validatedSectionIds($validated['section_ids'], $schoolRecord);

        $gradeRecord = DB::transaction(function () use ($gradeRecord, $sectionIds, $schoolRecord): Grade {
            $gradeRecord->sections()->syncWithPivotValues($sectionIds, ['school_id' => $schoolRecord->id]);

            return $gradeRecord->load('sections:id,title,status');
        });

        return response()->json(['data' => $this->gradeData($gradeRecord)]);
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

    /** @param array<int, int> $sectionIds
     * @return array<int, int>
     */
    private function validatedSectionIds(array $sectionIds, School $school): array
    {
        $sectionIds = array_map('intval', $sectionIds);
        $validCount = Section::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereIn('id', $sectionIds)
            ->count();

        if ($validCount !== count($sectionIds)) {
            throw ValidationException::withMessages([
                'section_ids' => ['Choose only active sections from this school.'],
            ]);
        }

        return $sectionIds;
    }

    /** @return array<string, mixed> */
    private function gradeData(Grade $grade): array
    {
        return [
            'id' => $grade->id,
            'title' => $grade->title,
            'status' => $grade->status,
            'sections' => $grade->sections->map(fn (Section $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'status' => $section->status,
            ])->values(),
        ];
    }
}
