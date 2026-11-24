<?php

namespace App\Services\Reports;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ReportFilters
{
    public static function forReport(Request $request, School $school, string $report, array $definition): array
    {
        $today = today();
        $dates = $definition['dates'] ?? true;
        $classFilters = ($definition['gradeOnly'] ?? false) ? ['grade'] : ['session', 'grade', 'section'];

        return Validator::make([
            'report' => $report,
            ...($dates ? [
                'from' => $request->query('from') ?? $today->copy()->startOfMonth()->toDateString(),
                'to' => $request->query('to') ?? $today->toDateString(),
            ] : []),
            ...($report === 'low-attendance' ? ['threshold' => $request->query('threshold') ?? 75] : []),
            ...$request->only($classFilters),
        ], [
            'report' => ['required', 'string'],
            'threshold' => ['sometimes', 'integer', 'between:0,100'],
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'session' => ['nullable', 'string', 'max:20', Rule::exists('enrollments', 'academic_year')->where('school_id', $school->id)],
            'grade' => ['nullable', 'integer', Rule::exists('grades', 'id')->where('school_id', $school->id)],
            'section' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_id', $school->id)],
        ])->validate();
    }
}
