<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeMaster;
use App\Models\School;
use App\Models\StudentFeeCharge;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeeMasterController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');
        $masters = $record->feeMasters()->with(['schoolSession:id,title', 'feeGroup:id,title', 'feeType:id,title'])
            ->orderBy('school_session_id')->orderBy('billing_month')->orderBy('fee_group_id')->orderBy('fee_type_id')->get();

        return response()->json(['data' => $masters->map(fn (FeeMaster $master) => $this->masterData($master))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $validated = $this->validated($request, $record);
        $this->validateUnique($validated, $record);
        $master = $record->feeMasters()->create($validated)->load(['schoolSession:id,title', 'feeGroup:id,title', 'feeType:id,title']);

        return response()->json(['data' => $this->masterData($master)], 201);
    }

    public function show(Request $request, int $school, int $feeMaster): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');
        $master = $record->feeMasters()->with(['schoolSession:id,title', 'feeGroup:id,title', 'feeType:id,title'])->findOrFail($feeMaster);

        return response()->json(['data' => $this->masterData($master)]);
    }

    public function update(Request $request, int $school, int $feeMaster): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $master = $record->feeMasters()->findOrFail($feeMaster);
        $validated = $this->validated($request, $record, true, $master);
        if (($validated['fine_type'] ?? $master->fine_type) === 'none' && ! array_key_exists('fine_value', $validated)) {
            $validated['fine_value'] = '0.00';
        }
        $candidate = [
            'school_session_id' => $validated['school_session_id'] ?? $master->school_session_id,
            'fee_group_id' => $validated['fee_group_id'] ?? $master->fee_group_id,
            'fee_type_id' => $validated['fee_type_id'] ?? $master->fee_type_id,
            'billing_month' => $validated['billing_month'] ?? $master->billing_month,
        ];
        $this->validateUnique($candidate, $record, $master->id);
        $master->update($validated);
        $master->load(['schoolSession:id,title', 'feeGroup:id,title', 'feeType:id,title']);

        return response()->json(['data' => $this->masterData($master)]);
    }

    public function destroy(Request $request, int $school, int $feeMaster): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $record->feeMasters()->findOrFail($feeMaster)->delete();

        return response()->json([], 204);
    }

    public function assignments(Request $request, int $school, int $feeMaster): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $master = $record->feeMasters()->findOrFail($feeMaster);
        $charges = StudentFeeCharge::query()->where('fee_master_id', $master->id)
            ->with('student:id,admission_number,first_name,last_name')
            ->orderBy('billing_month')->orderBy('id')->get();

        return response()->json(['data' => $charges->map(fn (StudentFeeCharge $charge) => [
            'id' => $charge->id,
            'student' => ['id' => $charge->student->id, 'admission_number' => $charge->student->admission_number, 'first_name' => $charge->student->first_name, 'last_name' => $charge->student->last_name],
            'billing_month' => $charge->billing_month,
            'amount' => $charge->amount,
            'due_date' => $charge->due_date->toDateString(),
            'status' => $charge->status,
        ])->values()]);
    }

    public function assignStudents(Request $request, int $school, int $feeMaster): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $master = $record->feeMasters()->with('feeGroup.grades')->findOrFail($feeMaster);
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct'],
            'grade_id' => ['sometimes', 'integer'],
            'section_id' => ['sometimes', 'integer'],
        ]);
        $session = $record->schoolSessions()->where('is_current', true)->first();
        if (! $session || $master->school_session_id !== $session->id) {
            throw ValidationException::withMessages(['fee_master' => ['Fee charges can only be assigned for the active academic year.']]);
        }
        $studentIds = array_map('intval', $validated['student_ids']);
        $academicYear = $session->starts_on->format('Y').'-'.$session->ends_on->format('Y');
        $students = $record->students()->where('status', 'active')->whereIn('students.id', $studentIds)
            ->whereHas('enrollments', function ($query) use ($academicYear, $validated): void {
                $query->where('academic_year', $academicYear)->where('status', 'active')
                    ->when(isset($validated['grade_id']), fn ($enrollments) => $enrollments->where('grade_id', $validated['grade_id']))
                    ->when(isset($validated['section_id']), fn ($enrollments) => $enrollments->where('section_id', $validated['section_id']));
            })->with(['enrollments' => fn ($query) => $query->where('academic_year', $academicYear)->where('status', 'active')])->get();
        if ($students->count() !== count($studentIds)) {
            throw ValidationException::withMessages(['student_ids' => ['Choose students actively enrolled in the selected grade or section for the active academic year.']]);
        }
        $allowedGrades = $master->feeGroup->grades->pluck('id')->all();
        if ($allowedGrades && $students->contains(fn ($student) => ! in_array($student->enrollments->first()?->grade_id, $allowedGrades, true))) {
            throw ValidationException::withMessages(['student_ids' => ['The fee group does not cover every selected student grade.']]);
        }

        $created = DB::transaction(function () use ($students, $master, $record, $session): int {
            $createdCount = 0;
            foreach ($students as $student) {
                $charge = StudentFeeCharge::query()->firstOrCreate([
                    'fee_master_id' => $master->id,
                    'student_id' => $student->id,
                    'billing_month' => $master->billing_month,
                ], [
                    'school_id' => $record->id,
                    'school_session_id' => $session->id,
                    'amount' => $master->amount,
                    'due_date' => $master->due_date,
                    'status' => 'open',
                ]);
                if ($charge->wasRecentlyCreated) {
                    $createdCount++;
                }
            }

            return $createdCount;
        });

        return response()->json(['data' => ['assigned' => $created, 'already_assigned' => count($studentIds) - $created]]);
    }

    private function validated(Request $request, School $school, bool $partial = false, ?FeeMaster $master = null): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $sameSchool = fn (string $table) => Rule::exists($table, 'id')->where(fn (Builder $query) => $query->where('school_id', $school->id));
        $validated = $request->validate([
            'school_session_id' => [...$required, 'integer', Rule::exists('school_sessions', 'id')->where(fn (Builder $query) => $query->where('school_id', $school->id)->where('status', 'active')->where('is_current', true))],
            'fee_group_id' => [...$required, 'integer', $sameSchool('fee_groups')],
            'fee_type_id' => [...$required, 'integer', $sameSchool('fee_types')],
            'billing_month' => [...$required, 'date_format:Y-m'],
            'due_date' => [...$required, 'date'],
            'amount' => [...$required, 'numeric', 'min:0', 'decimal:0,2'],
            'fine_type' => ['sometimes', Rule::in(['none', 'fixed', 'percentage'])],
            'fine_value' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
        $type = $validated['fine_type'] ?? $master?->fine_type ?? 'none';
        $value = (float) ($validated['fine_value'] ?? $master?->fine_value ?? 0);
        if ($type === 'none' && $value !== 0.0) {
            throw ValidationException::withMessages(['fine_value' => ['A fee with no fine must have a fine value of zero.']]);
        }
        if ($type === 'percentage' && $value > 100) {
            throw ValidationException::withMessages(['fine_value' => ['Percentage fines cannot exceed 100.']]);
        }

        return $validated;
    }

    /** @param array<string, mixed> $values */
    private function validateUnique(array $values, School $school, ?int $ignore = null): void
    {
        $query = $school->feeMasters()->where('school_session_id', $values['school_session_id'])
            ->where('fee_group_id', $values['fee_group_id'])->where('fee_type_id', $values['fee_type_id'])
            ->where('billing_month', $values['billing_month']);
        if ($ignore !== null) {
            $query->whereKeyNot($ignore);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['fee_type_id' => ['This fee type already has a master price for this group, session, and billing month.']]);
        }
    }

    /** @return array<string, mixed> */
    private function masterData(FeeMaster $master): array
    {
        return [
            'id' => $master->id,
            'school_session' => ['id' => $master->school_session_id, 'title' => $master->schoolSession->title],
            'fee_group' => ['id' => $master->fee_group_id, 'title' => $master->feeGroup->title],
            'fee_type' => ['id' => $master->fee_type_id, 'title' => $master->feeType->title],
            'billing_month' => $master->billing_month,
            'due_date' => $master->due_date->toDateString(),
            'amount' => $master->amount,
            'fine_type' => $master->fine_type,
            'fine_value' => $master->fine_value,
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
