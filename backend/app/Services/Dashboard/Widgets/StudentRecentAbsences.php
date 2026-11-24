<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final readonly class StudentRecentAbsences implements Widget
{
    public function __construct(private StudentAttendanceDays $days) {}

    public function definition(): array
    {
        return [
            'key' => 'student.recent_absences', 'title' => 'My recent absences', 'type' => 'list', 'size' => 'md',
            'scope' => 'branch', 'requiredModule' => 'attendance', 'requiredPermission' => 'student.self',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $dates = DB::query()->fromSub($this->days->query($context), 'days')
            ->where('percentage', 0)->orderByDesc('attendance_date')->limit(10)->pluck('attendance_date');

        return [
            'summary' => 'Most recent absences in the last 30 days.',
            'data' => [
                'rows' => $dates->map(fn (string $date) => ['title' => $date, 'meta' => 'Absent']),
                'empty' => 'No absences recorded in the last 30 days.',
            ],
        ];
    }
}
