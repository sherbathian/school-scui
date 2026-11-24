<?php

namespace App\Services\Reports;

use App\Models\School;
use Illuminate\Support\Facades\DB;

final class StaffReports
{
    public const CATALOGUE = [
        'staff-directory' => [
            'title' => 'Staff directory', 'columns' => ['Staff number', 'Name', 'Department', 'Designation', 'Joining date', 'Status'],
            'filename' => 'staff-directory.csv', 'module' => 'staff', 'dates' => false, 'fields' => [],
        ],
        'staff-leave' => [
            'title' => 'Staff leave balances', 'columns' => ['Staff number', 'Name', 'Leave type', 'Session', 'Allocated days', 'Used days', 'Remaining days'],
            'filename' => 'staff-leave.csv', 'module' => 'staff', 'dates' => false, 'fields' => ['session'],
        ],
    ];

    public function rows(string $report, School $school, array $filters): array
    {
        $staff = DB::table('staff')->where('staff.school_id', $school->id)->whereNull('staff.deleted_at')
            ->orderBy('staff.last_name')->orderBy('staff.first_name')->orderBy('staff.id')
            ->select(['staff.staff_no', 'staff.first_name', 'staff.last_name']);

        if ($report === 'staff-directory') {
            return $staff->join('departments', 'departments.id', '=', 'staff.department_id')
                ->join('designations', 'designations.id', '=', 'staff.designation_id')
                ->where('departments.school_id', $school->id)->where('designations.school_id', $school->id)
                ->addSelect('departments.title as department', 'designations.title as designation', 'staff.status')
                ->selectRaw('date(staff.joining_date) as joining_date')->get()
                ->map(fn (object $row) => [$row->staff_no, trim($row->first_name.' '.$row->last_name),
                    $row->department, $row->designation, $row->joining_date, $row->status])->all();
        }

        return $staff->join('staff_leave_balances as balances', 'balances.staff_id', '=', 'staff.id')
            ->join('leave_types', 'leave_types.id', '=', 'balances.leave_type_id')
            ->join('school_sessions', 'school_sessions.id', '=', 'balances.school_session_id')
            ->where('leave_types.school_id', $school->id)->where('school_sessions.school_id', $school->id)
            ->when($filters['session'] ?? null, fn ($query, $session) => $query->where('school_sessions.title', $session))
            ->orderByDesc('school_sessions.starts_on')->orderBy('school_sessions.id')->orderBy('leave_types.title')
            ->addSelect('leave_types.title as leave_type', 'school_sessions.title as session', 'balances.allocated', 'balances.used')->get()
            ->map(fn (object $row) => [$row->staff_no, trim($row->first_name.' '.$row->last_name),
                $row->leave_type, $row->session, number_format((float) $row->allocated, 1, '.', ''),
                number_format((float) $row->used, 1, '.', ''), number_format($row->allocated - $row->used, 1, '.', '')])->all();
    }
}
