<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $subjects = $schoolRecord->subjects()->with('grades:id,title,status')->orderBy('title')->get();

        return response()->json(['data' => $subjects->map(fn (Subject $subject) => $this->subjectData($subject))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('subjects', 'title')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'grade_ids' => ['sometimes', 'array', 'max:100'],
            'grade_ids.*' => ['integer', 'distinct'],
        ]);
        $gradeIds = $this->validatedGradeIds($validated['grade_ids'] ?? [], $schoolRecord);

        $subject = DB::transaction(function () use ($schoolRecord, $validated, $gradeIds): Subject {
            $subject = $schoolRecord->subjects()->create(['title' => $validated['title']]);
            if ($gradeIds !== []) {
                $subject->grades()->syncWithPivotValues($gradeIds, ['school_id' => $schoolRecord->id]);
            }

            return $subject->load('grades:id,title,status');
        });

        return response()->json(['data' => $this->subjectData($subject)], 201);
    }

    public function show(Request $request, int $school, int $subject): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $subjectRecord = $schoolRecord->subjects()->with('grades:id,title,status')->findOrFail($subject);

        return response()->json(['data' => $this->subjectData($subjectRecord)]);
    }

    public function update(Request $request, int $school, int $subject): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $subjectRecord = $schoolRecord->subjects()->findOrFail($subject);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('subjects', 'title')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))->ignore($subjectRecord->id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'grade_ids' => ['sometimes', 'array', 'max:100'],
            'grade_ids.*' => ['integer', 'distinct'],
        ]);
        $gradeIds = array_key_exists('grade_ids', $validated)
            ? $this->validatedGradeIds($validated['grade_ids'], $schoolRecord)
            : null;

        $subjectRecord = DB::transaction(function () use ($subjectRecord, $validated, $gradeIds, $schoolRecord): Subject {
            $subjectRecord->update(collect($validated)->except('grade_ids')->all());
            if ($gradeIds !== null) {
                $subjectRecord->grades()->syncWithPivotValues($gradeIds, ['school_id' => $schoolRecord->id]);
            }

            return $subjectRecord->load('grades:id,title,status');
        });

        return response()->json(['data' => $this->subjectData($subjectRecord)]);
    }

    public function destroy(Request $request, int $school, int $subject): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $subjectRecord = $schoolRecord->subjects()->findOrFail($subject);
        $subjectRecord->update(['status' => 'inactive']);

        return response()->json([], 204);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $schoolRecord = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $schoolRecord);

        return $schoolRecord;
    }

    /** @param array<int, int> $gradeIds
     * @return array<int, int>
     */
    private function validatedGradeIds(array $gradeIds, School $school): array
    {
        $gradeIds = array_map('intval', $gradeIds);
        $validCount = Grade::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->whereIn('id', $gradeIds)
            ->count();

        if ($validCount !== count($gradeIds)) {
            throw ValidationException::withMessages([
                'grade_ids' => ['Choose only active grades from this school.'],
            ]);
        }

        return $gradeIds;
    }

    /** @return array<string, mixed> */
    private function subjectData(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'title' => $subject->title,
            'status' => $subject->status,
            'grades' => $subject->grades->map(fn (Grade $grade) => [
                'id' => $grade->id,
                'title' => $grade->title,
                'status' => $grade->status,
            ])->values(),
        ];
    }
}
