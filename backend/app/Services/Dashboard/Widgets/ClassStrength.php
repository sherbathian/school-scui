<?php

namespace App\Services\Dashboard\Widgets;

use App\Services\Dashboard\DashboardContext;
use App\Services\Dashboard\Widget;
use Illuminate\Support\Facades\DB;

final class ClassStrength implements Widget
{
    public function definition(): array
    {
        return [
            'key' => 'branch.class_strength', 'title' => 'Class strength', 'type' => 'bar', 'size' => 'md',
            'scope' => 'branch', 'requiredModule' => 'students', 'requiredPermission' => 'students.view',
        ];
    }

    public function data(DashboardContext $context): array
    {
        $branch = $context->branch->id;
        // The student roster uses the latest active enrollment by ID.
        $current = DB::table('enrollments')->where('school_id', $branch)->where('status', 'active')
            ->selectRaw('max(id) as id')->groupBy('student_id');
        $items = DB::table('enrollments')->joinSub($current, 'current_enrollments', 'current_enrollments.id', '=', 'enrollments.id')
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('grades', 'grades.id', '=', 'enrollments.grade_id')
            ->where('students.school_id', $branch)->where('students.status', 'active')->where('grades.school_id', $branch)
            ->selectRaw('grades.title, count(*) as total')->groupBy('grades.id', 'grades.title')
            ->orderBy('grades.title')->orderBy('grades.id')->get()
            ->map(fn (object $grade) => ['label' => $grade->title, 'value' => (int) $grade->total]);

        return ['summary' => $items->map(fn (array $item) => $item['label'].': '.$item['value'])->join(', '),
            'data' => ['items' => $items]];
    }
}
