<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;

final readonly class StudentAttendanceTrend implements Widget
{
    public function __construct(private StudentAttendanceDays $days) {}

    public function definition(): array
    {
        return [
            'key' => 'student.attendance_trend', 'title' => 'My attendance over the last 30 days',
            'type' => 'trend', 'size' => 'lg', 'scope' => 'branch',
            'requiredModule' => 'attendance', 'requiredPermission' => 'student.self',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $recorded = $this->days->query($context)->pluck('percentage', 'attendance_date');
        $start = $context->asOf->subDays(29);
        $points = [];
        for ($offset = 0; $offset < 30; $offset++) {
            $date = $start->addDays($offset)->toDateString();
            $points[] = ['x' => $date, 'y' => $recorded->has($date) ? (int) $recorded->get($date) : null];
        }

        return [
            'summary' => $recorded->isEmpty() ? 'No attendance recorded in the last 30 days.' : 'Present and late count as attended.',
            'data' => ['points' => $points],
        ];
    }
}
