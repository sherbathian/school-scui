<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Staff;
use App\Models\StaffLeaveBalance;
use App\Models\Designation;
use App\Models\Department;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:first_name,last_name,staff_no,designation,department,status'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ]);
        $query = $schoolRecord->staff()->with(['designation:id,title', 'department:id,title']);
        $term = trim((string) ($validated['search'] ?? ''));
        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->where('staff_no', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('status', 'like', "%{$term}%")
                    ->orWhereHas('designation', fn ($lookup) => $lookup->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('department', fn ($lookup) => $lookup->where('title', 'like', "%{$term}%"));
            });
        }
        $sort = $validated['sort'] ?? 'last_name';
        $direction = $validated['direction'] ?? 'asc';
        $query->orderBy(match ($sort) {
            'designation' => Designation::query()->select('title')->whereColumn('designations.id', 'staff.designation_id')->limit(1),
            'department' => Department::query()->select('title')->whereColumn('departments.id', 'staff.department_id')->limit(1),
            default => $sort,
        }, $direction);
        $query->orderBy('first_name')->orderBy('id');
        $usePagination = $request->hasAny(['page', 'per_page', 'search', 'sort', 'direction']);
        if (! $usePagination) {
            $staff = $query->get();

            return response()->json(['data' => $staff->map(fn (Staff $person) => $this->staffData($person, $request->user()->hasSchoolPermission($schoolRecord, 'staff.manage')))->values()]);
        }
        $staff = $query->paginate($validated['per_page'] ?? 25)->appends($request->query());

        return response()->json([
            'data' => $staff->getCollection()->map(fn (Staff $person) => $this->staffData($person, $request->user()->hasSchoolPermission($schoolRecord, 'staff.manage')))->values(),
            'meta' => ['current_page' => $staff->currentPage(), 'last_page' => $staff->lastPage(), 'per_page' => $staff->perPage(), 'total' => $staff->total(), 'from' => $staff->firstItem(), 'to' => $staff->lastItem()],
        ]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $this->validatedStaff($request, $schoolRecord);
        $this->ensureLinkedUserIsMember($schoolRecord, $validated['user_id'] ?? null);
        if (isset($validated['email'])) {
            $validated['email'] = strtolower($validated['email']);
        }
        $person = $schoolRecord->staff()->create($validated)->load(['designation:id,title', 'department:id,title']);

        return response()->json(['data' => $this->staffData($person, true)], 201);
    }

    public function show(Request $request, int $school, int $staff): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $person = $schoolRecord->staff()->with(['designation:id,title', 'department:id,title'])->findOrFail($staff);

        return response()->json(['data' => $this->staffData($person, $request->user()->hasSchoolPermission($schoolRecord, 'staff.manage'))]);
    }

    public function update(Request $request, int $school, int $staff): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $person = $schoolRecord->staff()->findOrFail($staff);
        $validated = $this->validatedStaff($request, $schoolRecord, $person, true);
        $this->ensureLinkedUserIsMember($schoolRecord, $validated['user_id'] ?? null);
        if (isset($validated['email'])) {
            $validated['email'] = strtolower($validated['email']);
        }
        $person->update($validated);
        $person->load(['designation:id,title', 'department:id,title']);

        return response()->json(['data' => $this->staffData($person, true)]);
    }

    public function destroy(Request $request, int $school, int $staff): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $schoolRecord->staff()->findOrFail($staff)->delete();

        return response()->json([], 204);
    }

    public function leaveBalances(Request $request, int $school, int $staff): JsonResponse
    {
        $person = $this->staffForSchool($request, $school, $staff, 'view');
        $balances = $person->leaveBalances()->with(['leaveType:id,title', 'schoolSession:id,title'])->orderBy('school_session_id')->get();

        return response()->json(['data' => $balances]);
    }

    public function storeLeaveBalance(Request $request, int $school, int $staff): JsonResponse
    {
        $person = $this->staffForSchool($request, $school, $staff, 'manage');
        $validated = $this->validatedBalance($request, $person);
        $balance = $person->leaveBalances()->create($validated)->load(['leaveType:id,title', 'schoolSession:id,title']);

        return response()->json(['data' => $balance], 201);
    }

    public function updateLeaveBalance(Request $request, int $school, int $staff, int $balance): JsonResponse
    {
        $person = $this->staffForSchool($request, $school, $staff, 'manage');
        $record = $person->leaveBalances()->findOrFail($balance);
        $validated = $this->validatedBalance($request, $person, $record, true);
        $record->update($validated);

        return response()->json(['data' => $record->refresh()->load(['leaveType:id,title', 'schoolSession:id,title'])]);
    }

    public function destroyLeaveBalance(Request $request, int $school, int $staff, int $balance): JsonResponse
    {
        $person = $this->staffForSchool($request, $school, $staff, 'manage');
        $person->leaveBalances()->findOrFail($balance)->delete();

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function validatedStaff(Request $request, School $school, ?Staff $person = null, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $optional = $partial ? ['sometimes', 'nullable'] : ['nullable'];
        $unique = fn (string $table, string $column) => Rule::unique($table, $column)
            ->where(fn (Builder $query) => $query->where('school_id', $school->id))->ignore($person?->id);

        return $request->validate([
            'user_id' => [...$optional, 'integer', 'exists:users,id', Rule::unique('staff', 'user_id')->ignore($person?->id)],
            'designation_id' => [...$required, 'integer', Rule::exists('designations', 'id')->where('school_id', $school->id)],
            'department_id' => [...$required, 'integer', Rule::exists('departments', 'id')->where('school_id', $school->id)],
            'staff_no' => [...$required, 'string', 'max:30', $unique('staff', 'staff_no')],
            'first_name' => [...$required, 'string', 'max:100'],
            'last_name' => [...$required, 'string', 'max:100'],
            'father_name' => [...$optional, 'string', 'max:150'],
            'mother_name' => [...$optional, 'string', 'max:150'],
            'gender' => [...$required, Rule::in(['male', 'female'])],
            'marital_status' => [...$optional, Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'birth_date' => [...$required, 'date', 'before:today'],
            'joining_date' => [...$required, 'date'],
            'email' => [...$optional, 'email', 'max:255', $unique('staff', 'email')],
            'phone' => [...$optional, 'string', 'max:30'],
            'emergency_contact_number' => [...$optional, 'string', 'max:30'],
            'cnic' => [...$optional, 'regex:/^\d{13}$/', $unique('staff', 'cnic')],
            'photo_path' => [...$optional, 'string', 'max:255'],
            'address' => [...$optional, 'string'],
            'qualification' => [...$optional, 'string'],
            'work_experience' => [...$optional, 'string'],
            'basic_salary' => [...$required, 'numeric', 'min:0', 'decimal:0,2'],
            'bank_account_title' => [...$optional, 'string', 'max:150'],
            'bank_name' => [...$optional, 'string', 'max:150'],
            'bank_branch_name' => [...$optional, 'string', 'max:150'],
            'bank_account_number' => [...$optional, 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedBalance(Request $request, Staff $person, ?StaffLeaveBalance $balance = null, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $optional = $partial ? ['sometimes'] : [];
        $schoolId = $person->school_id;
        $validated = $request->validate([
            'leave_type_id' => [...$required, 'integer', Rule::exists('leave_types', 'id')->where('school_id', $schoolId)],
            'school_session_id' => [...$required, 'integer', Rule::exists('school_sessions', 'id')->where('school_id', $schoolId)],
            'allocated' => [...$required, 'numeric', 'min:0', 'decimal:0,1'],
            'used' => [...$optional, 'numeric', 'min:0', 'decimal:0,1'],
        ]);
        $allocated = (float) ($validated['allocated'] ?? $balance?->allocated);
        $used = (float) ($validated['used'] ?? $balance?->used ?? 0);
        if ($used > $allocated) {
            throw ValidationException::withMessages(['used' => ['Used leave days cannot exceed the allocated balance.']]);
        }
        $candidateType = $validated['leave_type_id'] ?? $balance?->leave_type_id;
        $candidateSession = $validated['school_session_id'] ?? $balance?->school_session_id;
        $duplicates = StaffLeaveBalance::query()->where('staff_id', $person->id)
            ->where('leave_type_id', $candidateType)->where('school_session_id', $candidateSession)
            ->when($balance, fn ($query) => $query->whereKeyNot($balance->id))->exists();
        if ($duplicates) {
            throw ValidationException::withMessages(['leave_type_id' => ['A balance already exists for this leave type and session.']]);
        }

        return $validated;
    }

    private function ensureLinkedUserIsMember(School $school, mixed $userId): void
    {
        if ($userId !== null && ! $school->users()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => ['Choose an account that belongs to this school.']]);
        }
    }

    private function staffForSchool(Request $request, int $school, int $staff, string $ability): Staff
    {
        $schoolRecord = $this->accessibleSchool($request, $school, $ability);

        return $schoolRecord->staff()->findOrFail($staff);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $record = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $record);

        return $record;
    }

    /** @return array<string, mixed> */
    private function staffData(Staff $person, bool $canManage): array
    {
        $data = [
            'id' => $person->id,
            'staff_no' => $person->staff_no,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'gender' => $person->gender,
            'designation' => $person->designation?->title,
            'department' => $person->department?->title,
            'joining_date' => $person->joining_date->toDateString(),
            'status' => $person->status,
        ];
        if (! $canManage) {
            return $data;
        }

        return $data + collect($person->only([
            'user_id', 'father_name', 'mother_name', 'marital_status', 'birth_date', 'email', 'phone',
            'emergency_contact_number', 'cnic', 'photo_path', 'address', 'qualification', 'work_experience',
            'basic_salary', 'bank_account_title', 'bank_name', 'bank_branch_name', 'bank_account_number',
        ]))->all();
    }
}
