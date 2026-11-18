<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentFeeCharge;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $validated = $request->validate([
            'academic_year' => ['sometimes', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:student_name,admission_number,phone,grade,section,status'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
        ]);
        $studentsQuery = $schoolRecord->students()
            ->with([
                'guardian:id,school_id,source_parent_id,father_name,father_phone,father_occupation,father_cnic,mother_name,mother_phone,mother_occupation,mother_cnic,is_guardian_other,guardian_name,guardian_relation,guardian_email,guardian_phone,guardian_occupation,guardian_cnic,guardian_address',
                'enrollments.grade:id,title', 'enrollments.section:id,title', 'lifecycleEvents',
            ])
            ->orderBy('last_name')->orderBy('first_name');

        $term = trim((string) ($validated['search'] ?? ''));
        if ($term !== '') {
            $studentsQuery->where(function ($query) use ($term): void {
                $query->where('admission_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('status', 'like', "%{$term}%")
                    ->orWhereHas('guardian', fn ($guardian) => $guardian->where('father_name', 'like', "%{$term}%")
                        ->orWhere('mother_name', 'like', "%{$term}%")
                        ->orWhere('father_phone', 'like', "%{$term}%")
                        ->orWhere('mother_phone', 'like', "%{$term}%"))
                    ->orWhereHas('enrollments.grade', fn ($grade) => $grade->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('enrollments.section', fn ($section) => $section->where('title', 'like', "%{$term}%"));
            });
        }

        if (isset($validated['academic_year'])) {
            $studentsQuery->whereHas('enrollments', fn ($query) => $query->where('academic_year', $validated['academic_year']));
        }

        $sort = $validated['sort'] ?? 'student_name';
        $direction = $validated['direction'] ?? 'asc';
        if ($sort === 'student_name') {
            $studentsQuery->reorder()->orderBy('last_name', $direction)->orderBy('first_name', $direction);
        } elseif (in_array($sort, ['grade', 'section'], true)) {
            $relation = $sort === 'grade' ? 'grade_id' : 'section_id';
            $studentsQuery->reorder()->orderBy(Enrollment::query()->select($relation)
                ->whereColumn('student_id', 'students.id')->where('status', 'active')->orderByDesc('id')->limit(1), $direction);
        } else {
            $studentsQuery->reorder()->orderBy($sort, $direction);
        }
        $studentsQuery->orderBy('id');
        $usePagination = $request->hasAny(['page', 'per_page', 'search', 'sort', 'direction']);
        if (! $usePagination) {
            $students = $studentsQuery->get();

            return response()->json(['data' => $students->map(fn (Student $student) => $this->studentData($student))->values()]);
        }
        $students = $studentsQuery->paginate($validated['per_page'] ?? 25)->appends($request->query());

        return response()->json([
            'data' => $students->getCollection()->map(fn (Student $student) => $this->studentData($student))->values(),
            'meta' => ['current_page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'per_page' => $students->perPage(), 'total' => $students->total(), 'from' => $students->firstItem(), 'to' => $students->lastItem()],
        ]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $validated = $request->validate([
            'admission_number' => ['required', 'string', 'max:50', Rule::unique('students', 'admission_number')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'guardian_id' => ['nullable', 'integer', Rule::exists('guardians', 'id')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'full_name' => ['nullable', 'string', 'max:180'],
            'admission_type' => ['nullable', 'string', 'max:40'], 'previous_admission_number' => ['nullable', 'string', 'max:50'],
            'admitted_class' => ['nullable', 'string', 'max:80'], 'current_class_label' => ['nullable', 'string', 'max:80'],
            'caste_or_tribe' => ['nullable', 'string', 'max:100'], 'fee_rate' => ['nullable', 'numeric', 'min:0'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'], 'fee_waiver' => ['nullable', 'string', 'max:80'],
            'arrears_due' => ['nullable', 'numeric', 'min:0'], 'withdrawn_from_class' => ['nullable', 'string', 'max:80'],
            'source_marks_obtained' => ['nullable', 'numeric', 'min:0'], 'source_marks_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'source_data' => ['nullable', 'array'], 'source_guardian_data' => ['nullable', 'array'],
            'gender' => ['nullable', Rule::in(['male', 'female'])], 'religion' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'], 'admission_date' => ['nullable', 'date'], 'leave_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'leave', 'alumni', 'inactive'])],
            'b_form_number' => ['nullable', 'string', 'max:15', Rule::unique('students', 'b_form_number')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'previous_school_details' => ['nullable', 'string'], 'note' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'], 'b_form_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'guardian' => ['nullable', 'array'], 'guardian.father_name' => ['nullable', 'string', 'max:255'], 'guardian.father_phone' => ['nullable', 'string', 'max:50'], 'guardian.father_occupation' => ['nullable', 'string', 'max:255'], 'guardian.father_cnic' => ['nullable', 'string', 'max:30'],
            'guardian.mother_name' => ['nullable', 'string', 'max:255'], 'guardian.mother_phone' => ['nullable', 'string', 'max:50'], 'guardian.mother_occupation' => ['nullable', 'string', 'max:255'], 'guardian.mother_cnic' => ['nullable', 'string', 'max:30'],
            'guardian.is_guardian_other' => ['nullable', 'boolean'], 'guardian.guardian_name' => ['nullable', 'string', 'max:255'], 'guardian.guardian_relation' => ['nullable', 'string', 'max:100'], 'guardian.guardian_email' => ['nullable', 'email', 'max:255'], 'guardian.guardian_phone' => ['nullable', 'string', 'max:50'], 'guardian.guardian_occupation' => ['nullable', 'string', 'max:255'], 'guardian.guardian_cnic' => ['nullable', 'string', 'max:30'], 'guardian.guardian_address' => ['nullable', 'string'],
        ]);
        $guardian = $validated['guardian'] ?? null;
        unset($validated['guardian']);
        $photo = $validated['photo'] ?? null;
        $bFormDocument = $validated['b_form_document'] ?? null;
        unset($validated['photo'], $validated['b_form_document']);
        if ($photo) {
            $validated['photo_path'] = $photo->store("students/{$schoolRecord->id}/photos", 'local');
        }
        if ($bFormDocument) {
            $validated['b_form_document_path'] = $bFormDocument->store("students/{$schoolRecord->id}/documents", 'local');
        }
        $validated['full_name'] = $validated['full_name'] ?? trim($validated['first_name'].' '.$validated['last_name']);
        if ($guardian && collect($guardian)->filter()->isNotEmpty()) {
            if (! ($guardian['is_guardian_other'] ?? false) && empty($guardian['father_phone']) && empty($guardian['mother_phone'])) {
                throw ValidationException::withMessages(['guardian.father_phone' => ['Enter a father, mother, or other guardian phone number.']]);
            }
            if (($guardian['is_guardian_other'] ?? false) && (empty($guardian['guardian_name']) || empty($guardian['guardian_relation']) || empty($guardian['guardian_phone']))) {
                throw ValidationException::withMessages(['guardian.guardian_name' => ['Name, relation, and phone are required for an alternate guardian.']]);
            }
        }
        $student = DB::transaction(function () use ($schoolRecord, $validated, $guardian) {
            if ($guardian && collect($guardian)->filter()->isNotEmpty()) {
                $validated['guardian_id'] = $schoolRecord->guardians()->create($guardian)->id;
            }

            return $schoolRecord->students()->create($validated);
        });

        return response()->json(['data' => $this->studentData($student->load('guardian', 'enrollments.grade:id,title', 'enrollments.section:id,title', 'lifecycleEvents'))], 201);
    }

    public function show(Request $request, int $school, int $student): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $studentRecord = $schoolRecord->students()->with('guardian', 'enrollments.grade:id,title', 'enrollments.section:id,title', 'lifecycleEvents')->findOrFail($student);
        $data = $this->studentData($studentRecord);
        if ($studentRecord->photo_path) {
            $data['photo_url'] = URL::temporarySignedRoute('students.media', now()->addMinutes(30), [
                'school' => $schoolRecord->id,
                'student' => $studentRecord->id,
                'kind' => 'photo',
            ], absolute: false);
        }
        if ($studentRecord->b_form_document_path) {
            $data['b_form_document_url'] = URL::temporarySignedRoute('students.media', now()->addMinutes(30), [
                'school' => $schoolRecord->id,
                'student' => $studentRecord->id,
                'kind' => 'document',
            ], absolute: false);
        }
        if (Gate::allows('manage', $schoolRecord)) {
            $data['fee_history'] = $this->feeHistory($studentRecord);
            $data['legacy_fee_history'] = $studentRecord->legacyFeeSnapshots()->orderByDesc('billing_month')->get()
                ->map(fn ($snapshot): array => $snapshot->only(['billing_month', 'balance_amount', 'received_amount', 'balance_source_label', 'received_source_label']))
                ->values();
        }

        return response()->json(['data' => $data]);
    }

    public function media(int $school, int $student, string $kind): StreamedResponse
    {
        $studentRecord = School::query()->findOrFail($school)->students()->findOrFail($student);
        $path = match ($kind) {
            'photo' => $studentRecord->photo_path,
            'document' => $studentRecord->b_form_document_path,
            default => null,
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function update(Request $request, int $school, int $student): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $studentRecord = $schoolRecord->students()->findOrFail($student);
        $validated = $request->validate([
            'guardian_id' => ['sometimes', 'nullable', 'integer', Rule::exists('guardians', 'id')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))],
            'admission_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('students', 'admission_number')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))->ignore($studentRecord->id)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'admission_type' => ['sometimes', 'nullable', 'string', 'max:40'], 'previous_admission_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'admitted_class' => ['sometimes', 'nullable', 'string', 'max:80'], 'current_class_label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'caste_or_tribe' => ['sometimes', 'nullable', 'string', 'max:100'], 'fee_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fee_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'], 'fee_waiver' => ['sometimes', 'nullable', 'string', 'max:80'],
            'arrears_due' => ['sometimes', 'nullable', 'numeric', 'min:0'], 'withdrawn_from_class' => ['sometimes', 'nullable', 'string', 'max:80'],
            'source_marks_obtained' => ['sometimes', 'nullable', 'numeric', 'min:0'], 'source_marks_percentage' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'source_data' => ['sometimes', 'nullable', 'array'], 'source_guardian_data' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'leave', 'alumni', 'inactive'])],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])], 'religion' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'], 'phone' => ['sometimes', 'nullable', 'string', 'max:50'], 'address' => ['sometimes', 'nullable', 'string'],
            'birth_date' => ['sometimes', 'nullable', 'date'], 'admission_date' => ['sometimes', 'nullable', 'date'], 'leave_date' => ['sometimes', 'nullable', 'date'],
            'b_form_number' => ['sometimes', 'nullable', 'string', 'max:15', Rule::unique('students', 'b_form_number')->where(fn (Builder $query) => $query->where('school_id', $schoolRecord->id))->ignore($studentRecord->id)],
            'previous_school_details' => ['sometimes', 'nullable', 'string'], 'note' => ['sometimes', 'nullable', 'string'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:5120'], 'b_form_document' => ['sometimes', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'], 'remove_b_form_document' => ['sometimes', 'boolean'],
            'guardian' => ['sometimes', 'array'],
            'guardian.father_name' => ['nullable', 'string', 'max:255'], 'guardian.father_phone' => ['nullable', 'string', 'max:50'], 'guardian.father_occupation' => ['nullable', 'string', 'max:255'], 'guardian.father_cnic' => ['nullable', 'string', 'max:30'],
            'guardian.mother_name' => ['nullable', 'string', 'max:255'], 'guardian.mother_phone' => ['nullable', 'string', 'max:50'], 'guardian.mother_occupation' => ['nullable', 'string', 'max:255'], 'guardian.mother_cnic' => ['nullable', 'string', 'max:30'],
            'guardian.is_guardian_other' => ['nullable', 'boolean'], 'guardian.guardian_name' => ['nullable', 'string', 'max:255'], 'guardian.guardian_relation' => ['nullable', 'string', 'max:100'], 'guardian.guardian_email' => ['nullable', 'email', 'max:255'], 'guardian.guardian_phone' => ['nullable', 'string', 'max:50'], 'guardian.guardian_occupation' => ['nullable', 'string', 'max:255'], 'guardian.guardian_cnic' => ['nullable', 'string', 'max:30'], 'guardian.guardian_address' => ['nullable', 'string'],
        ]);
        $photo = $validated['photo'] ?? null;
        $bFormDocument = $validated['b_form_document'] ?? null;
        $removePhoto = (bool) ($validated['remove_photo'] ?? false);
        $removeBFormDocument = (bool) ($validated['remove_b_form_document'] ?? false);
        unset($validated['photo'], $validated['b_form_document']);
        unset($validated['remove_photo'], $validated['remove_b_form_document']);
        if (! array_key_exists('full_name', $validated) && (array_key_exists('first_name', $validated) || array_key_exists('last_name', $validated))) {
            $validated['full_name'] = trim(($validated['first_name'] ?? $studentRecord->first_name).' '.($validated['last_name'] ?? $studentRecord->last_name));
        }
        if ($photo || $removePhoto) {
            if ($studentRecord->photo_path) {
                Storage::disk('local')->delete($studentRecord->photo_path);
            }
            $validated['photo_path'] = $photo ? $photo->store("students/{$schoolRecord->id}/photos", 'local') : null;
        }
        if ($bFormDocument || $removeBFormDocument) {
            if ($studentRecord->b_form_document_path) {
                Storage::disk('local')->delete($studentRecord->b_form_document_path);
            }
            $validated['b_form_document_path'] = $bFormDocument ? $bFormDocument->store("students/{$schoolRecord->id}/documents", 'local') : null;
        }
        if (isset($validated['guardian'])) {
            $guardian = $validated['guardian'];
            unset($validated['guardian']);
            if ($studentRecord->guardian) {
                $studentRecord->guardian->update($guardian);
            } elseif (collect($guardian)->filter()->isNotEmpty()) {
                $validated['guardian_id'] = $schoolRecord->guardians()->create($guardian)->id;
            }
        }
        $studentRecord->update($validated);

        return response()->json(['data' => $this->studentData($studentRecord->load('guardian', 'enrollments.grade:id,title', 'enrollments.section:id,title', 'lifecycleEvents'))]);
    }

    public function destroy(Request $request, int $school, int $student): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $studentRecord = $schoolRecord->students()->findOrFail($student);
        $studentRecord->update(['status' => 'inactive']);
        $studentRecord->enrollments()->where('status', 'active')->update(['status' => 'withdrawn', 'ended_on' => now()->toDateString()]);

        return response()->json([], 204);
    }

    public function enroll(Request $request, int $school, int $student): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $studentRecord = $schoolRecord->students()->where('status', 'active')->findOrFail($student);
        $validated = $request->validate([
            'grade_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'academic_year' => ['sometimes', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'enrolled_on' => ['sometimes', 'date'],
        ]);
        $activeSession = $schoolRecord->schoolSessions()->where('is_current', true)->first();
        if (! $activeSession) {
            throw ValidationException::withMessages(['academic_year' => ['Set an active academic year before enrolling students.']]);
        }
        $academicYear = $activeSession->starts_on->format('Y').'-'.$activeSession->ends_on->format('Y');
        if (isset($validated['academic_year']) && $validated['academic_year'] !== $academicYear) {
            throw ValidationException::withMessages(['academic_year' => ['New enrollments must use the school’s active academic year.']]);
        }
        $gradeId = (int) $validated['grade_id'];
        $sectionId = (int) $validated['section_id'];
        $enrolledOn = $validated['enrolled_on'] ?? now()->toDateString();
        $previousEnrollmentEndDate = CarbonImmutable::parse($enrolledOn)->subDay()->toDateString();
        $isAssignedActiveClass = DB::table('grade_section')
            ->join('grades', function ($join) {
                $join->on('grades.id', '=', 'grade_section.grade_id')
                    ->on('grades.school_id', '=', 'grade_section.school_id');
            })
            ->join('sections', function ($join) {
                $join->on('sections.id', '=', 'grade_section.section_id')
                    ->on('sections.school_id', '=', 'grade_section.school_id');
            })
            ->where('grade_section.school_id', $schoolRecord->id)
            ->where('grade_section.grade_id', $gradeId)
            ->where('grade_section.section_id', $sectionId)
            ->where('grades.status', 'active')
            ->where('sections.status', 'active')
            ->exists();

        if (! $isAssignedActiveClass) {
            throw ValidationException::withMessages([
                'section_id' => ['Choose an active section assigned to the selected grade in this school.'],
            ]);
        }

        $enrollment = DB::transaction(function () use ($studentRecord, $schoolRecord, $gradeId, $sectionId, $enrolledOn, $previousEnrollmentEndDate): Enrollment {
            $studentRecord = Student::query()->whereKey($studentRecord->id)->lockForUpdate()->firstOrFail();
            $studentRecord->enrollments()
                ->where('status', 'active')
                ->update(['status' => 'completed', 'ended_on' => $previousEnrollmentEndDate]);

            return $studentRecord->enrollments()->create([
                'school_id' => $schoolRecord->id,
                'grade_id' => $gradeId,
                'section_id' => $sectionId,
                'academic_year' => $academicYear,
                'status' => 'active',
                'enrolled_on' => $enrolledOn,
            ])->load('grade:id,title', 'section:id,title');
        });

        return response()->json(['data' => $this->enrollmentData($enrollment)], 201);
    }

    public function withdraw(Request $request, int $school, int $student): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $studentRecord = $schoolRecord->students()->where('status', 'active')->findOrFail($student);
        $validated = $request->validate(['event_date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($studentRecord, $schoolRecord, $validated): void {
            $studentRecord->update(['status' => 'leave', 'leave_date' => $validated['event_date']]);
            $studentRecord->enrollments()->where('status', 'active')->update(['status' => 'withdrawn', 'ended_on' => $validated['event_date']]);
            $studentRecord->lifecycleEvents()->create(['school_id' => $schoolRecord->id, 'event_type' => 'withdrawal', 'event_date' => $validated['event_date'], 'reason' => $validated['reason'] ?? null]);
        });

        return response()->json(['data' => $this->studentData($studentRecord->refresh()->load('guardian', 'enrollments.grade:id,title', 'enrollments.section:id,title', 'lifecycleEvents'))]);
    }

    public function rejoin(Request $request, int $school, int $student): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'manage');
        $studentRecord = $schoolRecord->students()->whereIn('status', ['leave', 'inactive'])->findOrFail($student);
        $validated = $request->validate(['grade_id' => ['required', 'integer'], 'section_id' => ['required', 'integer'], 'event_date' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $activeSession = $schoolRecord->schoolSessions()->where('is_current', true)->first();
        if (! $activeSession) {
            throw ValidationException::withMessages(['academic_year' => ['Set an active academic year before rejoining a student.']]);
        }
        $academicYear = $activeSession->starts_on->format('Y').'-'.$activeSession->ends_on->format('Y');
        $isAssignedActiveClass = DB::table('grade_section')
            ->join('grades', fn ($join) => $join->on('grades.id', '=', 'grade_section.grade_id')->on('grades.school_id', '=', 'grade_section.school_id'))
            ->join('sections', fn ($join) => $join->on('sections.id', '=', 'grade_section.section_id')->on('sections.school_id', '=', 'grade_section.school_id'))
            ->where('grade_section.school_id', $schoolRecord->id)
            ->where('grade_section.grade_id', $validated['grade_id'])
            ->where('grade_section.section_id', $validated['section_id'])
            ->where('grades.status', 'active')->where('sections.status', 'active')->exists();
        if (! $isAssignedActiveClass) {
            throw ValidationException::withMessages(['section_id' => ['Choose an active section assigned to the selected grade.']]);
        }

        $enrollment = DB::transaction(function () use ($studentRecord, $schoolRecord, $validated, $academicYear): Enrollment {
            $studentRecord = Student::query()->whereKey($studentRecord->id)->lockForUpdate()->firstOrFail();
            $studentRecord->enrollments()->where('status', 'active')->update(['status' => 'completed', 'ended_on' => CarbonImmutable::parse($validated['event_date'])->subDay()->toDateString()]);
            $studentRecord->update(['status' => 'active', 'leave_date' => null]);
            $studentRecord->lifecycleEvents()->create(['school_id' => $schoolRecord->id, 'event_type' => 'rejoining', 'event_date' => $validated['event_date'], 'reason' => $validated['reason'] ?? null]);

            return $studentRecord->enrollments()->create(['school_id' => $schoolRecord->id, 'grade_id' => $validated['grade_id'], 'section_id' => $validated['section_id'], 'academic_year' => $academicYear, 'status' => 'active', 'enrolled_on' => $validated['event_date']])->load('grade:id,title', 'section:id,title');
        });

        return response()->json(['data' => $this->enrollmentData($enrollment)], 201);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $schoolRecord = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $schoolRecord);

        return $schoolRecord;
    }

    /** @return array<string, mixed> */
    private function studentData(Student $student): array
    {
        $enrollments = $student->enrollments->sortByDesc('id')->values();
        $currentEnrollment = $enrollments->firstWhere('status', 'active');

        return [
            'id' => $student->id,
            'guardian_id' => $student->guardian_id,
            'admission_number' => $student->admission_number,
            'admission_type' => $student->admission_type,
            'previous_admission_number' => $student->previous_admission_number,
            'admitted_class' => $student->admitted_class,
            'current_class_label' => $student->current_class_label,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'full_name' => $student->full_name ?: trim($student->first_name.' '.$student->last_name),
            'status' => $student->status,
            'gender' => $student->gender,
            'religion' => $student->religion,
            'email' => $student->email,
            'phone' => $student->phone,
            'address' => $student->address,
            'birth_date' => $student->birth_date?->toDateString(),
            'b_form_number' => $student->b_form_number,
            'photo_path' => $student->photo_path,
            'b_form_document_path' => $student->b_form_document_path,
            'admission_date' => $student->admission_date?->toDateString(),
            'leave_date' => $student->leave_date?->toDateString(),
            'previous_school_details' => $student->previous_school_details,
            'note' => $student->note,
            'caste_or_tribe' => $student->caste_or_tribe,
            'fee_rate' => $student->fee_rate,
            'fee_amount' => $student->fee_amount,
            'fee_waiver' => $student->fee_waiver,
            'arrears_due' => $student->arrears_due,
            'withdrawn_from_class' => $student->withdrawn_from_class,
            'source_marks_obtained' => $student->source_marks_obtained,
            'source_marks_percentage' => $student->source_marks_percentage,
            'guardian' => $student->guardian?->only(['id', 'source_parent_id', 'father_name', 'father_phone', 'father_occupation', 'father_cnic', 'mother_name', 'mother_phone', 'mother_occupation', 'mother_cnic', 'is_guardian_other', 'guardian_name', 'guardian_relation', 'guardian_email', 'guardian_phone', 'guardian_occupation', 'guardian_cnic', 'guardian_address']),
            'current_enrollment' => $currentEnrollment ? $this->enrollmentData($currentEnrollment) : null,
            'enrollments' => $enrollments->map(fn (Enrollment $enrollment) => $this->enrollmentData($enrollment))->all(),
            'lifecycle_events' => $student->lifecycleEvents->sortByDesc('event_date')->values()->map(fn ($event): array => ['id' => $event->id, 'event_type' => $event->event_type, 'event_date' => $event->event_date->toDateString(), 'reason' => $event->reason])->all(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function feeHistory(Student $student): array
    {
        $invoices = $student->feeInvoices()
            ->with(['feeType:id,title', 'payments'])
            ->orderByDesc('academic_year')->orderByDesc('id')->get()
            ->map(function (FeeInvoice $invoice): array {
                $paidCents = (int) $invoice->payments->sum('amount_cents');
                $balanceCents = max(0, $invoice->amount_cents - $paidCents);
                $status = match (true) {
                    $invoice->status === 'void' => 'void',
                    $balanceCents === 0 => 'paid',
                    $paidCents > 0 => 'partial',
                    $invoice->due_date?->lt(today()) => 'overdue',
                    default => 'open',
                };

                return [
                    'id' => $invoice->id,
                    'kind' => 'invoice',
                    'fee_type' => $invoice->description ?: $invoice->feeType?->title,
                    'academic_year' => $invoice->academic_year,
                    'billing_month' => null,
                    'due_date' => $invoice->due_date?->toDateString(),
                    'amount' => number_format($invoice->amount_cents / 100, 2, '.', ''),
                    'amount_paid' => number_format($paidCents / 100, 2, '.', ''),
                    'balance' => number_format($balanceCents / 100, 2, '.', ''),
                    'status' => $status,
                    'payments' => $invoice->payments->map(fn ($payment): array => [
                        'amount' => number_format($payment->amount_cents / 100, 2, '.', ''),
                        'paid_on' => $payment->paid_on->toDateString(),
                        'reference' => $payment->reference,
                    ])->values()->all(),
                ];
            });

        $monthlyCharges = $student->feeCharges()
            ->with(['feeMaster.feeType:id,title', 'schoolSession:id,starts_on,ends_on'])
            ->orderByDesc('billing_month')->orderByDesc('id')->get()
            ->map(fn (StudentFeeCharge $charge): array => [
                'id' => $charge->id,
                'kind' => 'monthly',
                'fee_type' => $charge->feeMaster?->feeType?->title ?? 'Monthly fee',
                'academic_year' => $charge->schoolSession?->starts_on?->format('Y').'-'.$charge->schoolSession?->ends_on?->format('Y'),
                'billing_month' => $charge->billing_month,
                'due_date' => $charge->due_date->toDateString(),
                'amount' => $charge->amount,
                'amount_paid' => $charge->status === 'paid' ? $charge->amount : null,
                'balance' => $charge->status === 'paid' ? '0.00' : $charge->amount,
                'status' => $charge->status,
                'payments' => [],
            ]);

        return $invoices->concat($monthlyCharges)->sortByDesc(fn (array $fee): string => $fee['billing_month'] ?? $fee['academic_year'])->values()->all();
    }

    /** @return array<string, mixed> */
    private function enrollmentData(Enrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'academic_year' => $enrollment->academic_year,
            'status' => $enrollment->status,
            'enrolled_on' => $enrollment->enrolled_on->toDateString(),
            'ended_on' => $enrollment->ended_on?->toDateString(),
            'grade' => ['id' => $enrollment->grade->id, 'title' => $enrollment->grade->title],
            'section' => ['id' => $enrollment->section->id, 'title' => $enrollment->section->title],
        ];
    }
}
