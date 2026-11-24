<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class AttendanceTrend implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'branch.attendance_trend', 'title' => 'Attendance over the last 14 days', 'type' => 'trend', 'size' => 'lg',
            'scope' => 'branch', 'requiredModule' => 'attendance', 'requiredPermission' => 'attendance.view',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $today = $context->asOf->toDateString();
        $start = $context->asOf->subDays(13);
        $totals = DB::table('attendance_records')->where('school_id', $context->branch->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $today])
            ->whereIn('status', ['present', 'late', 'absent'])
            ->selectRaw('attendance_date, count(*) as recorded')
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as attended', ['present', 'late'])
            ->groupBy('attendance_date')->get()->keyBy('attendance_date');

        $points = [];
        for ($offset = 0; $offset < 14; $offset++) {
            $date = $start->addDays($offset)->toDateString();
            $day = $totals->get($date);
            $points[] = ['x' => $date, 'y' => $day ? (int) round(100 * $day->attended / $day->recorded) : null];
        }

        return [
            'summary' => $totals->isEmpty()
                ? 'No attendance recorded in the last 14 days.'
                : 'Daily percentage of recorded attendance marked present or late.',
            'data' => ['points' => $points],
        ];
    }
}
