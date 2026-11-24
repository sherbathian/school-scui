<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class StudentAttendanceDays
{
    public function query(DashboardContext $context): Builder
    {
        $branch = $context->branch->id;

        return DB::table('attendance_records as attendance')
            ->join('enrollments', 'enrollments.id', '=', 'attendance.enrollment_id')
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('school_user', 'school_user.student_id', '=', 'students.id')
            ->where('attendance.school_id', $branch)->where('enrollments.school_id', $branch)
            ->where('students.school_id', $branch)->where('school_user.school_id', $branch)
            ->where('school_user.user_id', $context->user->id)
            ->where('school_user.role', 'student')->where('school_user.is_active', true)
            ->whereBetween('attendance.attendance_date', [
                $context->asOf->subDays(29)->toDateString(), $context->asOf->toDateString(),
            ])->whereIn('attendance.status', ['present', 'late', 'absent'])
            ->select('attendance.attendance_date')
            ->selectRaw('max(case when attendance.status in (?, ?) then 100 else 0 end) as percentage', ['present', 'late'])
            ->groupBy('attendance.attendance_date');
    }
}
