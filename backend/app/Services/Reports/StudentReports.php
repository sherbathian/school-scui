<?php

namespace App\Services\Reports;

use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final class StudentReports
{
    public const CATALOGUE = [
        'student-strength' => [
            'title' => 'Student strength by class', 'columns' => ['Grade', 'Section', 'Students'],
            'filename' => 'student-strength.csv', 'module' => 'students', 'dates' => false,
        ],
        'student-movement' => [
            'title' => 'New and withdrawn students', 'columns' => ['Date', 'Movement', 'Admission number', 'Student', 'Grade', 'Section'],
            'filename' => 'student-movement.csv', 'module' => 'students',
        ],
        'admissions-funnel' => [
            'title' => 'Admissions funnel', 'columns' => ['Stage', 'Inquiries'],
            'filename' => 'admissions-funnel.csv', 'module' => 'inquiries', 'gradeOnly' => true,
        ],
    ];

    public function rows(string $report, School $school, array $filters): array
    {
        return match ($report) {
            'student-strength' => $this->strength($school, $filters),
            'student-movement' => $this->movement($school, $filters),
            'admissions-funnel' => $this->admissions($school, $filters),
        };
    }

    private function strength(School $school, array $filters): array
    {
        $roster = DB::table('enrollments')->where('school_id', $school->id)->where('status', 'active')
            ->when($filters['session'] ?? null, fn ($query, $session) => $query->where('academic_year', $session))
            ->selectRaw('max(id) as id')->groupBy('student_id');
        $records = DB::table('enrollments as enrollment')->joinSub($roster, 'roster', 'roster.id', '=', 'enrollment.id')
            ->join('students', 'students.id', '=', 'enrollment.student_id')
            ->where('students.school_id', $school->id)->where('students.status', 'active');

        return $this->withClass($records, $school, $filters)
            ->selectRaw('grades.title as grade, sections.title as section, count(*) as total')
            ->groupBy('grades.id', 'grades.title', 'sections.id', 'sections.title')
            ->orderBy('grades.title')->orderBy('sections.title')->get()
            ->map(fn (object $row) => [$row->grade, $row->section, (int) $row->total])->all();
    }

    private function movement(School $school, array $filters): array
    {
        $new = DB::table('enrollments as enrollment')->where('enrollment.school_id', $school->id)
            ->whereDate('enrollment.enrolled_on', '>=', $filters['from'])
            ->whereDate('enrollment.enrolled_on', '<=', $filters['to'])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('enrollments as prior')
                    ->whereColumn('prior.school_id', 'enrollment.school_id')->whereColumn('prior.student_id', 'enrollment.student_id')
                    ->where(fn (Builder $orders) => $orders->whereRaw('date(prior.enrolled_on) < date(enrollment.enrolled_on)')
                        ->orWhere(fn (Builder $sameDay) => $sameDay->whereRaw('date(prior.enrolled_on) = date(enrollment.enrolled_on)')
                            ->whereColumn('prior.id', '<', 'enrollment.id')));
            })->join('students', 'students.id', '=', 'enrollment.student_id')
            ->selectRaw("date(enrollment.enrolled_on) as movement_date, 'New' as movement");
        $withdrawn = DB::table('student_lifecycle_events as event')->where('event.school_id', $school->id)
            ->where('event.event_type', 'withdrawal')->whereBetween('event.event_date', [$filters['from'], $filters['to']])
            ->join('students', 'students.id', '=', 'event.student_id')
            ->leftJoin('enrollments as enrollment', function (JoinClause $join): void {
                $join->on('enrollment.student_id', '=', 'event.student_id')->on('enrollment.school_id', '=', 'event.school_id')
                    ->where('enrollment.id', '=', fn (Builder $query) => $query->from('enrollments as current')
                        ->select('current.id')->whereColumn('current.student_id', 'event.student_id')
                        ->whereColumn('current.school_id', 'event.school_id')->whereRaw('date(current.enrolled_on) <= event.event_date')
                        ->orderByDesc('current.enrolled_on')->orderByDesc('current.id')->limit(1));
            })->selectRaw("event.event_date as movement_date, 'Withdrawn' as movement");

        foreach ([$new, $withdrawn] as $records) {
            $this->withClass($records, $school, $filters)->where('students.school_id', $school->id)
                ->addSelect(['students.admission_number', 'students.first_name', 'students.last_name',
                    'grades.title as grade', 'sections.title as section']);
        }

        return $new->unionAll($withdrawn)->orderBy('movement_date')->orderBy('movement')->orderBy('admission_number')->get()
            ->map(fn (object $row) => [$row->movement_date, $row->movement, $row->admission_number,
                trim($row->first_name.' '.$row->last_name), $row->grade, $row->section])->all();
    }

    private function withClass(Builder $records, School $school, array $filters): Builder
    {
        return $records->leftJoin('grades', fn (JoinClause $join) => $join
            ->on('grades.id', '=', 'enrollment.grade_id')->where('grades.school_id', $school->id))
            ->leftJoin('sections', fn (JoinClause $join) => $join
                ->on('sections.id', '=', 'enrollment.section_id')->where('sections.school_id', $school->id))
            ->when($filters['session'] ?? null, fn ($query, $session) => $query->where('enrollment.academic_year', $session))
            ->when($filters['grade'] ?? null, fn ($query, $grade) => $query->where('grades.id', $grade))
            ->when($filters['section'] ?? null, fn ($query, $section) => $query->where('sections.id', $section));
    }

    private function admissions(School $school, array $filters): array
    {
        $counts = DB::table('student_inquiries')->where('school_id', $school->id)
            ->whereBetween('inquiry_date', [$filters['from'], $filters['to']])
            ->when($filters['grade'] ?? null, fn ($query, $grade) => $query->where('requested_grade_id', $grade))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $stages = ['new' => 'New', 'contacted' => 'Contacted', 'visit_scheduled' => 'Visit scheduled',
            'registered' => 'Registered', 'rejected' => 'Rejected'];

        return collect($stages)->map(fn (string $label, string $status) => [$label, (int) $counts->get($status, 0)])->values()->all();
    }
}
