<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class AttendanceToday implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'branch.attendance_today', 'title' => 'Attendance today', 'type' => 'bar', 'size' => 'md',
            'scope' => 'branch', 'requiredModule' => 'attendance', 'requiredPermission' => 'attendance.view',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $totals = DB::table('attendance_records')->where('school_id', $context->branch->id)
            ->where('attendance_date', $context->asOf->toDateString())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $items = array_map(fn (string $status) => ['label' => ucfirst($status), 'value' => (int) $totals->get($status, 0)],
            ['present', 'absent', 'late', 'excused']);

        return ['summary' => collect($items)->map(fn (array $item) => $item['label'].': '.$item['value'])->join(', '),
            'data' => ['items' => $items]];
    }
}
