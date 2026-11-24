<?php

namespace App\Services\Reports;

use App\Models\School;
use Illuminate\Support\Facades\DB;

final class AttendanceReports
{
    public const DAILY_COLUMNS = ['Date', 'Grade', 'Section', 'Present', 'Absent', 'Late', 'Excused'];

    public function daily(School $school, array $filters): array
    {
        return DB::table('attendance_records as attendance')
            ->join('enrollments', 'enrollments.id', '=', 'attendance.enrollment_id')
            ->join('grades', 'grades.id', '=', 'enrollments.grade_id')
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->where('attendance.school_id', $school->id)->where('enrollments.school_id', $school->id)
            ->where('grades.school_id', $school->id)->where('sections.school_id', $school->id)
            ->whereBetween('attendance.attendance_date', [$filters['from'], $filters['to']])
            ->when($filters['session'] ?? null, fn ($query, $session) => $query->where('enrollments.academic_year', $session))
            ->when($filters['grade'] ?? null, fn ($query, $grade) => $query->where('grades.id', $grade))
            ->when($filters['section'] ?? null, fn ($query, $section) => $query->where('sections.id', $section))
            ->select('attendance.attendance_date', 'grades.title as grade', 'sections.title as section')
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as present', ['present'])
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as absent', ['absent'])
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as late', ['late'])
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as excused', ['excused'])
            ->groupBy('attendance.attendance_date', 'grades.id', 'grades.title', 'sections.id', 'sections.title')
            ->orderBy('attendance.attendance_date')->orderBy('grades.title')->orderBy('sections.title')
            ->get()->map(fn (object $row) => [
                $row->attendance_date, $row->grade, $row->section,
                (int) $row->present, (int) $row->absent, (int) $row->late, (int) $row->excused,
            ])->all();
    }
}
