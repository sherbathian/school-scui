<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class StudentAttendanceMonth implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'student.attendance_month', 'title' => 'My attendance this month',
            'type' => 'kpi', 'size' => 'sm', 'scope' => 'branch',
            'requiredModule' => 'attendance', 'requiredPermission' => 'student.self',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $branch = $context->branch->id;
        $month = $context->asOf->startOfMonth();
        $linkedStudent = DB::table('school_user')->join('students', function ($join): void {
            $join->on('students.id', '=', 'school_user.student_id')
                ->on('students.school_id', '=', 'school_user.school_id');
        })->where('school_user.school_id', $branch)
            ->where('school_user.user_id', $context->user->id)->where('school_user.role', 'student')
            ->where('school_user.is_active', true)->select('students.id');
        $enrollments = DB::table('enrollments')->where('school_id', $branch)
            ->whereIn('student_id', $linkedStudent)->select('id');

        $counts = DB::table('attendance_records')->where('school_id', $branch)
            ->whereIn('enrollment_id', $enrollments)
            ->where('attendance_date', '>=', $month->toDateString())
            ->where('attendance_date', '<', $month->addMonth()->toDateString())
            ->whereIn('status', ['present', 'late', 'absent'])
            ->selectRaw('count(distinct attendance_date) as recorded')
            ->selectRaw('count(distinct case when status in (?, ?) then attendance_date end) as attended', ['present', 'late'])
            ->first();

        $recorded = (int) $counts->recorded;
        $attended = (int) $counts->attended;

        return [
            'summary' => $recorded ? "{$attended} of {$recorded} recorded days attended." : 'No attendance recorded this month.',
            'data' => ['value' => $recorded ? (int) round(100 * $attended / $recorded) : null, 'unit' => '%'],
        ];
    }
}
