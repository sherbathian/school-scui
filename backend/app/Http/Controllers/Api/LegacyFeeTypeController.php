<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegacyFeeType;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LegacyFeeTypeController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate(['academic_year' => ['sometimes', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20']]);
        $types = $record->legacyFeeTypes()
            ->when(isset($validated['academic_year']), fn ($query) => $query->where('academic_year', $validated['academic_year']))
            ->orderBy('title')->get();

        return response()->json(['data' => $types->map(fn (LegacyFeeType $type) => $this->typeData($type))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $activeYear = $this->activeAcademicYear($record);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
        ]);
        if ($validated['academic_year'] !== $activeYear) {
            throw ValidationException::withMessages(['academic_year' => ['Fee items must use the school’s active academic year.']]);
        }
        $this->validateUniqueTitle($validated['title'], $validated['academic_year'], $record);
        $type = $record->legacyFeeTypes()->create([
            'title' => $validated['title'],
            'academic_year' => $validated['academic_year'],
            'amount_cents' => $this->toCents($validated['amount']),
            'status' => 'active',
        ]);

        return response()->json(['data' => $this->typeData($type)], 201);
    }

    public function update(Request $request, int $school, int $feeType): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $type = $record->legacyFeeTypes()->findOrFail($feeType);
        $activeYear = $this->activeAcademicYear($record);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'academic_year' => ['sometimes', 'required', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
        if (isset($validated['academic_year']) && $validated['academic_year'] !== $activeYear) {
            throw ValidationException::withMessages(['academic_year' => ['Fee items must use the school’s active academic year.']]);
        }
        $title = $validated['title'] ?? $type->title;
        $year = $validated['academic_year'] ?? $type->academic_year;
        if ($title !== $type->title || $year !== $type->academic_year) {
            $this->validateUniqueTitle($title, $year, $record, $type->id);
        }
        if (isset($validated['amount'])) {
            $validated['amount_cents'] = $this->toCents($validated['amount']);
            unset($validated['amount']);
        }
        $type->update($validated);

        return response()->json(['data' => $this->typeData($type)]);
    }

    public function destroy(Request $request, int $school, int $feeType): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $record->legacyFeeTypes()->findOrFail($feeType)->update(['status' => 'inactive']);

        return response()->json([], 204);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }

    private function validateUniqueTitle(string $title, string $year, School $school, ?int $ignore = null): void
    {
        $query = LegacyFeeType::query()->where('school_id', $school->id)->where('title', $title)->where('academic_year', $year);
        if ($ignore !== null) {
            $query->where('id', '!=', $ignore);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['title' => ['A fee item with this name already exists for this academic year.']]);
        }
    }

    private function toCents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function activeAcademicYear(School $school): string
    {
        $session = $school->schoolSessions()->where('is_current', true)->first();
        if (! $session) {
            throw ValidationException::withMessages(['academic_year' => ['Set an active academic year before managing fees.']]);
        }

        return $session->starts_on->format('Y').'-'.$session->ends_on->format('Y');
    }

    /** @return array<string, mixed> */
    private function typeData(LegacyFeeType $type): array
    {
        return [
            'id' => $type->id,
            'title' => $type->title,
            'academic_year' => $type->academic_year,
            'amount' => number_format($type->amount_cents / 100, 2, '.', ''),
            'status' => $type->status,
        ];
    }
}
