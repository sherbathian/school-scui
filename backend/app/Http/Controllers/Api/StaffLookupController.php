<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffLookupController extends Controller
{
    private const TYPES = [
        'designations' => [Designation::class, 'designation_id', 'designations'],
        'departments' => [Department::class, 'department_id', 'departments'],
        'leave-types' => [LeaveType::class, 'leave_type_id', 'leaveTypes'],
    ];

    public function index(Request $request, int $school, string $lookupType): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $model = $this->model($lookupType);
        $rows = $model::query()->where('school_id', $schoolRecord->id)->orderBy('title')->get(['id', 'title']);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, int $school, string $lookupType): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $model = $this->model($lookupType);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100', Rule::unique((new $model)->getTable(), 'title')->where(fn ($query) => $query->where('school_id', $schoolRecord->id))],
        ]);
        $relation = self::TYPES[$lookupType][2];
        $row = $schoolRecord->{$relation}()->create($validated);

        return response()->json(['data' => $row->only(['id', 'title'])], 201);
    }

    public function update(Request $request, int $school, string $lookupType, int $lookup): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $model = $this->model($lookupType);
        $row = $model::query()->where('school_id', $schoolRecord->id)->findOrFail($lookup);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100', Rule::unique($row->getTable(), 'title')->where(fn ($query) => $query->where('school_id', $schoolRecord->id))->ignore($row->id)],
        ]);
        $row->update($validated);

        return response()->json(['data' => $row->only(['id', 'title'])]);
    }

    public function destroy(Request $request, int $school, string $lookupType, int $lookup): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        [$model, $foreignKey] = self::TYPES[$lookupType];
        $row = $model::query()->where('school_id', $schoolRecord->id)->findOrFail($lookup);
        $isUsedByStaff = $lookupType !== 'leave-types' && DB::table('staff')->where($foreignKey, $row->id)->exists();
        $isUsedByBalance = $lookupType === 'leave-types'
            && DB::table('staff_leave_balances')->where($foreignKey, $row->id)->exists();
        if ($isUsedByStaff || $isUsedByBalance) {
            throw ValidationException::withMessages(['lookup' => ['This item is in use and cannot be deleted.']]);
        }
        $row->delete();

        return response()->json([], 204);
    }

    private function model(string $lookupType): string
    {
        abort_unless(isset(self::TYPES[$lookupType]), 404);

        return self::TYPES[$lookupType][0];
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }
}
