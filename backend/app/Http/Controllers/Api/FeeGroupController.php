<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeGroup;
use App\Models\Grade;
use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeeGroupController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');
        $groups = $record->feeGroups()->with('grades:id,title')->orderBy('title')->get();

        return response()->json(['data' => $groups->map(fn (FeeGroup $group) => $this->groupData($group))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $this->validated($request, $record);
        $gradeIds = $this->validGradeIds($validated['grade_ids'] ?? [], $record);
        $group = DB::transaction(function () use ($record, $validated, $gradeIds): FeeGroup {
            $group = $record->feeGroups()->create(collect($validated)->except('grade_ids')->all());
            $group->grades()->syncWithPivotValues($gradeIds, ['school_id' => $group->school_id]);

            return $group->load('grades:id,title');
        });

        return response()->json(['data' => $this->groupData($group)], 201);
    }

    public function show(Request $request, int $school, int $feeGroup): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');
        $group = $record->feeGroups()->with('grades:id,title')->findOrFail($feeGroup);

        return response()->json(['data' => $this->groupData($group)]);
    }

    public function update(Request $request, int $school, int $feeGroup): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $group = $record->feeGroups()->findOrFail($feeGroup);
        $validated = $this->validated($request, $record, $group->id, true);
        $gradeIds = array_key_exists('grade_ids', $validated) ? $this->validGradeIds($validated['grade_ids'], $record) : null;
        $group = DB::transaction(function () use ($group, $validated, $gradeIds): FeeGroup {
            $group->update(collect($validated)->except('grade_ids')->all());
            if ($gradeIds !== null) {
                $group->grades()->syncWithPivotValues($gradeIds, ['school_id' => $group->school_id]);
            }

            return $group->load('grades:id,title');
        });

        return response()->json(['data' => $this->groupData($group)]);
    }

    public function destroy(Request $request, int $school, int $feeGroup): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $group = $record->feeGroups()->findOrFail($feeGroup);
        if ($group->feeMasters()->exists()) {
            throw ValidationException::withMessages(['fee_group' => ['This fee group is used by a fee master and cannot be deleted.']]);
        }
        $group->delete();

        return response()->json([], 204);
    }

    private function validated(Request $request, School $school, ?int $ignore = null, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'title' => [...$required, 'string', 'max:150', Rule::unique('fee_groups', 'title')->where(fn (Builder $query) => $query->where('school_id', $school->id))->ignore($ignore)],
            'description' => ['sometimes', 'nullable', 'string'],
            'allows_discount' => ['sometimes', 'boolean'],
            'grade_ids' => ['sometimes', 'array', 'max:100'],
            'grade_ids.*' => ['integer', 'distinct'],
        ]);
    }

    /** @return list<int> */
    private function validGradeIds(array $gradeIds, School $school): array
    {
        $gradeIds = array_values(array_unique(array_map('intval', $gradeIds)));
        $count = Grade::query()->where('school_id', $school->id)->whereIn('id', $gradeIds)->count();
        if ($count !== count($gradeIds)) {
            throw ValidationException::withMessages(['grade_ids' => ['Choose grades from this school only.']]);
        }

        return $gradeIds;
    }

    /** @return array<string, mixed> */
    private function groupData(FeeGroup $group): array
    {
        return [
            'id' => $group->id,
            'title' => $group->title,
            'description' => $group->description,
            'allows_discount' => $group->allows_discount,
            'grades' => $group->grades->map(fn (Grade $grade) => ['id' => $grade->id, 'title' => $grade->title])->values(),
        ];
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }
}
