<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\StudentInquiry;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentInquiryController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'view');

        return response()->json(['data' => $record->studentInquiries()->with(['requestedGrade:id,title', 'student:id,admission_number'])->orderByDesc('inquiry_date')->orderByDesc('id')->get()->map(fn (StudentInquiry $inquiry) => $this->inquiryData($inquiry))->values()]);
    }

    public function store(Request $request, int $school): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $inquiry = $record->studentInquiries()->create($request->validate($this->rules($record)));

        return response()->json(['data' => $this->inquiryData($inquiry->load('requestedGrade:id,title', 'student:id,admission_number'))], 201);
    }

    public function update(Request $request, int $school, int $inquiry): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $inquiryRecord = $record->studentInquiries()->findOrFail($inquiry);
        $inquiryRecord->update($request->validate($this->rules($record, true)));

        return response()->json(['data' => $this->inquiryData($inquiryRecord->refresh()->load('requestedGrade:id,title', 'student:id,admission_number'))]);
    }

    public function destroy(Request $request, int $school, int $inquiry): JsonResponse
    {
        $record = $this->accessibleSchool($request, $school, 'manage');
        $record->studentInquiries()->findOrFail($inquiry)->delete();

        return response()->json([], 204);
    }

    private function rules(School $school, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'first_name' => [$presence, 'string', 'max:100'],
            'last_name' => [$presence, 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:180'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'requested_grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')->where(fn (Builder $query) => $query->where('school_id', $school->id))],
            'inquiry_date' => [$presence, 'date'],
            'source' => ['nullable', 'string', 'max:80'],
            'status' => [$partial ? 'sometimes' : 'nullable', Rule::in(['new', 'contacted', 'visit_scheduled', 'registered', 'rejected'])],
            'notes' => ['nullable', 'string'],
            'student_id' => ['sometimes', 'nullable', 'integer', Rule::exists('students', 'id')->where(fn (Builder $query) => $query->where('school_id', $school->id))],
        ];
    }

    /** @return array<string, mixed> */
    private function inquiryData(StudentInquiry $inquiry): array
    {
        return [...$inquiry->only(['id', 'first_name', 'last_name', 'guardian_name', 'guardian_phone', 'guardian_email', 'requested_grade_id', 'source', 'status', 'notes', 'student_id']),
            'inquiry_date' => $inquiry->inquiry_date?->toDateString(),
            'requested_grade' => $inquiry->requestedGrade?->title,
            'admission_number' => $inquiry->student?->admission_number,
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
