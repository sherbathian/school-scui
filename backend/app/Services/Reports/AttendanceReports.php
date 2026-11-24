<?php

namespace App\Services\Reports;

use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AttendanceReports
{
    public const DAILY_COLUMNS = ['Date', 'Grade', 'Section', 'Present', 'Absent', 'Late', 'Excused'];

    public const CATALOGUE = [
        'daily' => [
            'title' => 'Attendance by day and class', 'columns' => self::DAILY_COLUMNS, 'filename' => 'attendance.csv',
        ],
        'monthly' => [
            'title' => 'Monthly attendance by class',
            'columns' => ['Month', 'Grade', 'Section', 'Present', 'Absent', 'Late', 'Excused', 'Attendance (%)'],
            'filename' => 'attendance-monthly.csv',
        ],
        'low-attendance' => [
            'title' => 'Low-attendance students',
            'columns' => ['Admission number', 'Student', 'Recorded days', 'Attended days', 'Attendance (%)'],
            'filename' => 'low-attendance.csv',
        ],
    ];

    public function rows(string $report, School $school, array $filters): array
    {
        $records = DB::table('attendance_records as attendance')
            ->join('enrollments', 'enrollments.id', '=', 'attendance.enrollment_id')
            ->join('grades', 'grades.id', '=', 'enrollments.grade_id')
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->where('attendance.school_id', $school->id)->where('enrollments.school_id', $school->id)
            ->where('grades.school_id', $school->id)->where('sections.school_id', $school->id)
            ->whereBetween('attendance.attendance_date', [$filters['from'], $filters['to']])
            ->when($filters['session'] ?? null, fn ($query, $session) => $query->where('enrollments.academic_year', $session))
            ->when($filters['grade'] ?? null, fn ($query, $grade) => $query->where('grades.id', $grade))
            ->when($filters['section'] ?? null, fn ($query, $section) => $query->where('sections.id', $section));

        return $report === 'low-attendance'
            ? $this->lowAttendance($records, $school, (int) $filters['threshold'])
            : $this->classCounts($records, $report === 'monthly');
    }

    private function classCounts(Builder $records, bool $monthly): array
    {
        $period = $monthly ? 'substr(attendance.attendance_date, 1, 7)' : 'attendance.attendance_date';

        return $records->select('grades.title as grade', 'sections.title as section')->selectRaw("$period as period")
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as present', ['present'])
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as absent', ['absent'])
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as late', ['late'])
            ->selectRaw('sum(case when attendance.status = ? then 1 else 0 end) as excused', ['excused'])
            ->groupByRaw($period)->groupBy('grades.id', 'grades.title', 'sections.id', 'sections.title')
            ->orderBy('period')->orderBy('grades.title')->orderBy('sections.title')
            ->get()->map(function (object $row) use ($monthly): array {
                $counts = [$row->period, $row->grade, $row->section,
                    (int) $row->present, (int) $row->absent, (int) $row->late, (int) $row->excused];
                if ($monthly) {
                    $attended = (int) $row->present + (int) $row->late;
                    $recorded = $attended + (int) $row->absent;
                    $counts[] = $recorded ? round(100 * $attended / $recorded, 2) : null;
                }

                return $counts;
            })->all();
    }

    private function lowAttendance(Builder $records, School $school, int $threshold): array
    {
        $days = $records->whereIn('attendance.status', ['present', 'absent', 'late'])
            ->select('enrollments.student_id', 'attendance.attendance_date')
            ->selectRaw('max(case when attendance.status in (?, ?) then 1 else 0 end) as attended', ['present', 'late'])
            ->groupBy('enrollments.student_id', 'attendance.attendance_date');

        return DB::query()->fromSub($days, 'days')->join('students', 'students.id', '=', 'days.student_id')
            ->where('students.school_id', $school->id)
            ->select('students.admission_number', 'students.first_name', 'students.last_name')
            ->selectRaw('count(*) as recorded, sum(days.attended) as attended')
            ->groupBy('students.id', 'students.admission_number', 'students.first_name', 'students.last_name')
            ->havingRaw('100 * sum(days.attended) < ? * count(*)', [$threshold])
            ->orderBy('students.last_name')->orderBy('students.first_name')->orderBy('students.id')
            ->get()->map(fn (object $row) => [
                $row->admission_number, trim($row->first_name.' '.$row->last_name),
                (int) $row->recorded, (int) $row->attended, round(100 * $row->attended / $row->recorded, 2),
            ])->all();
    }
}
