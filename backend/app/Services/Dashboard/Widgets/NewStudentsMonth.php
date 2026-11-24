<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class NewStudentsMonth implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'branch.new_students_month', 'title' => 'New students this month', 'type' => 'kpi', 'size' => 'sm',
            'scope' => 'branch', 'requiredModule' => 'students', 'requiredPermission' => 'students.view',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $month = $context->asOf->startOfMonth();
        $count = DB::table('students')->where('school_id', $context->branch->id)
            ->where('created_at', '>=', $month)->where('created_at', '<', $month->addMonth())->count();

        return ['summary' => 'Student records created this month.', 'data' => ['value' => $count]];
    }
}
