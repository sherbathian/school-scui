<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeType;
use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeeTypeController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $record->feeTypes()->orderBy('title')->get(['id', 'title', 'description'])]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150', Rule::unique('fee_types', 'title')->where(fn (Builder $query) => $query->where('school_id', $record->id))],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);
        $feeType = $record->feeTypes()->create($validated);

        return response()->json(['data' => $this->feeTypeData($feeType)], 201);
    }

    public function show(Request $request, int $school, int $feeType): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $this->feeTypeData($record->feeTypes()->findOrFail($feeType))]);
    }

    public function update(Request $request, int $school, int $feeType): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $type = $record->feeTypes()->findOrFail($feeType);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('fee_types', 'title')->where(fn (Builder $query) => $query->where('school_id', $record->id))->ignore($type->id)],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);
        $type->update($validated);

        return response()->json(['data' => $this->feeTypeData($type->refresh())]);
    }

    public function destroy(Request $request, int $school, int $feeType): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $type = $record->feeTypes()->findOrFail($feeType);
        if ($type->feeMasters()->exists()) {
            throw ValidationException::withMessages(['fee_type' => ['This fee type is used by a fee master and cannot be deleted.']]);
        }
        $type->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function feeTypeData(FeeType $type): array
    {
        return ['id' => $type->id, 'title' => $type->title, 'description' => $type->description];
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }
}
