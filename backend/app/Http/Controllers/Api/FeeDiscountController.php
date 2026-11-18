<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeDiscount;
use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeeDiscountController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $record->feeDiscounts()->orderBy('title')->get()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $this->validated($request, $record);
        $discount = $record->feeDiscounts()->create($validated);

        return response()->json(['data' => $discount], 201);
    }

    public function show(Request $request, int $school, int $feeDiscount): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $record->feeDiscounts()->findOrFail($feeDiscount)]);
    }

    public function update(Request $request, int $school, int $feeDiscount): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $discount = $record->feeDiscounts()->findOrFail($feeDiscount);
        $validated = $this->validated($request, $record, $discount->id, true);
        $discount->update($validated);

        return response()->json(['data' => $discount->refresh()]);
    }

    public function destroy(Request $request, int $school, int $feeDiscount): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $record->feeDiscounts()->findOrFail($feeDiscount)->delete();

        return response()->json([], 204);
    }

    private function validated(Request $request, School $school, ?int $ignore = null, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $validated = $request->validate([
            'title' => [...$required, 'string', 'max:150', Rule::unique('fee_discounts', 'title')->where(fn (Builder $query) => $query->where('school_id', $school->id))->ignore($ignore)],
            'discount_type' => [...$required, Rule::in(['percentage', 'fixed'])],
            'value' => [...$required, 'numeric', 'min:0', 'decimal:0,2'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);
        $current = $ignore ? FeeDiscount::query()->whereKey($ignore)->first() : null;
        $type = $validated['discount_type'] ?? $current?->discount_type;
        $value = $validated['value'] ?? $current?->value ?? $request->input('value');
        if ($type === 'percentage' && (float) $value > 100) {
            throw ValidationException::withMessages(['value' => ['Percentage discounts cannot exceed 100.']]);
        }

        return $validated;
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }
}
