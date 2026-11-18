<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'view');
        $validated = $request->validate([
            'grade_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $this->ensureActiveClass($schoolRecord, (int) $validated['grade_id'], (int) $validated['section_id']);
        $enrollments = $this->eligibleEnrollments($schoolRecord, $validated)->with([
            'student:id,admission_number,first_name,last_name,status',
            'grade:id,title',
            'section:id,title',
            'attendanceRecords' => fn ($query) => $query->whereDate('attendance_date', $validated['attendance_date']),
        ])->orderBy('students.last_name')->orderBy('students.first_name')->get();

        return response()->json(['data' => $enrollments->map(fn (Enrollment $enrollment) => $this->attendanceData($enrollment))->values()]);
    }

    public function update(Request $request, int $school): JsonResponse
    {
        $schoolRecord = $this->accessibleSchool($request, $school, 'recordAttendance');
        $validated = $request->validate([
            'grade_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'max:20'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
        ]);
        $this->ensureActiveClass($schoolRecord, (int) $validated['grade_id'], (int) $validated['section_id']);
        $eligibleEnrollmentIds = $this->eligibleEnrollments($schoolRecord, $validated)->pluck('id')->map(fn (int $id) => (int) $id)->all();
        $submittedEnrollmentIds = collect($validated['records'])->pluck('enrollment_id')->map(fn (int $id) => (int) $id)->all();
        sort($eligibleEnrollmentIds);
        sort($submittedEnrollmentIds);

        if ($eligibleEnrollmentIds === [] || $eligibleEnrollmentIds !== $submittedEnrollmentIds) {
            throw ValidationException::withMessages([
                'records' => ['Submit one attendance status for every enrolled student in this class.'],
            ]);
        }

        DB::transaction(function () use ($schoolRecord, $validated, $request): void {
            foreach ($validated['records'] as $record) {
                $attendance = AttendanceRecord::query()
                    ->where('school_id', $schoolRecord->id)
                    ->where('enrollment_id', $record['enrollment_id'])
                    ->whereDate('attendance_date', $validated['attendance_date'])
                    ->first();

                if ($attendance) {
                    $attendance->update([
                        'status' => $record['status'],
                        'marked_by_user_id' => $request->user()->id,
                    ]);
                } else {
                    AttendanceRecord::query()->create([
                        'school_id' => $schoolRecord->id,
                        'enrollment_id' => $record['enrollment_id'],
                        'attendance_date' => $validated['attendance_date'],
                        'status' => $record['status'],
                        'marked_by_user_id' => $request->user()->id,
                    ]);
                }
            }
        });

        $enrollments = $this->eligibleEnrollments($schoolRecord, $validated)->with([
            'student:id,admission_number,first_name,last_name,status',
            'grade:id,title',
            'section:id,title',
            'attendanceRecords' => fn ($query) => $query->whereDate('attendance_date', $validated['attendance_date']),
        ])->orderBy('students.last_name')->orderBy('students.first_name')->get();

        return response()->json(['data' => $enrollments->map(fn (Enrollment $enrollment) => $this->attendanceData($enrollment))->values()]);
    }

    private function accessibleSchool(Request $request, int $school, string $ability): School
    {
        $schools = $request->user()->isSuperAdmin() ? School::query() : $request->user()->activeSchools();
        $schoolRecord = $schools->whereKey($school)->firstOrFail();
        Gate::authorize($ability, $schoolRecord);

        return $schoolRecord;
    }

    private function ensureActiveClass(School $school, int $gradeId, int $sectionId): void
    {
        $isActiveClass = DB::table('grade_section')
            ->join('grades', function ($join) {
                $join->on('grades.id', '=', 'grade_section.grade_id')
                    ->on('grades.school_id', '=', 'grade_section.school_id');
            })
            ->join('sections', function ($join) {
                $join->on('sections.id', '=', 'grade_section.section_id')
                    ->on('sections.school_id', '=', 'grade_section.school_id');
            })
            ->where('grade_section.school_id', $school->id)
            ->where('grade_section.grade_id', $gradeId)
            ->where('grade_section.section_id', $sectionId)
            ->where('grades.status', 'active')
            ->where('sections.status', 'active')
            ->exists();

        if (! $isActiveClass) {
            throw ValidationException::withMessages([
                'section_id' => ['Choose an active section assigned to the selected grade in this school.'],
            ]);
        }
    }

    /** @param array<string, mixed> $selection
     * @return EloquentBuilder<Enrollment>
     */
    private function eligibleEnrollments(School $school, array $selection): EloquentBuilder
    {
        return Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.school_id', $school->id)
            ->where('enrollments.grade_id', $selection['grade_id'])
            ->where('enrollments.section_id', $selection['section_id'])
            ->where('enrollments.academic_year', $selection['academic_year'])
            ->whereDate('enrollments.enrolled_on', '<=', $selection['attendance_date'])
            ->where(function (EloquentBuilder $query) use ($selection) {
                $query->whereNull('enrollments.ended_on')
                    ->orWhereDate('enrollments.ended_on', '>=', $selection['attendance_date']);
            })
            ->select('enrollments.*');
    }

    /** @return array<string, mixed> */
    private function attendanceData(Enrollment $enrollment): array
    {
        return [
            'enrollment_id' => $enrollment->id,
            'student' => [
                'id' => $enrollment->student->id,
                'admission_number' => $enrollment->student->admission_number,
                'first_name' => $enrollment->student->first_name,
                'last_name' => $enrollment->student->last_name,
            ],
            'grade' => ['id' => $enrollment->grade->id, 'title' => $enrollment->grade->title],
            'section' => ['id' => $enrollment->section->id, 'title' => $enrollment->section->title],
            'academic_year' => $enrollment->academic_year,
            'status' => $enrollment->attendanceRecords->first()?->status,
        ];
    }
}
