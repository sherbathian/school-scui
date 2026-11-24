<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class UnmarkedClasses implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'branch.unmarked_classes', 'title' => 'Classes to mark today', 'type' => 'list', 'size' => 'md',
            'scope' => 'branch', 'requiredModule' => 'attendance', 'requiredPermission' => 'attendance.view',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $branch = $context->branch->id;
        $today = $context->asOf->toDateString();
        $teacher = $context->user->schoolAccessRole($context->branch) === 'teacher';
        $current = DB::table('enrollments')->where('school_id', $branch)->where('status', 'active')
            ->selectRaw('max(id) as id')->groupBy('student_id');

        $classes = DB::table('enrollments')->joinSub($current, 'roster', 'roster.id', '=', 'enrollments.id')
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('grades', 'grades.id', '=', 'enrollments.grade_id')
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->leftJoin('attendance_records as attendance', function (JoinClause $join) use ($today): void {
                $join->on('attendance.enrollment_id', '=', 'enrollments.id')
                    ->on('attendance.school_id', '=', 'enrollments.school_id')
                    ->where('attendance.attendance_date', $today);
            })
            ->where('students.school_id', $branch)->where('students.status', 'active')
            ->where('grades.school_id', $branch)->where('sections.school_id', $branch)
            ->selectRaw('grades.title as grade, sections.title as section, count(distinct enrollments.id) as total')
            ->selectRaw('count(distinct attendance.enrollment_id) as marked')
            ->groupBy('grades.id', 'grades.title', 'sections.id', 'sections.title')
            ->havingRaw($teacher ? 'count(distinct attendance.enrollment_id) < count(distinct enrollments.id)' : 'count(attendance.enrollment_id) = 0')
            ->orderBy('grades.title')->orderBy('sections.title')->get();

        return [
            'summary' => $teacher ? 'Branch classes with unmarked attendance.' : 'Classes with no attendance entries today.',
            'data' => [
                'rows' => $classes->map(fn (object $class) => [
                    'title' => "$class->grade / $class->section",
                    'meta' => ($class->total - $class->marked)." of $class->total unmarked",
                ]),
                'empty' => 'No classes to show.',
            ],
        ];
    }
}
