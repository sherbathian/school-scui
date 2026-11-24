<?php

namespace App\Services\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ReportFilters
{
    public static function fields(array $definition): array
    {
        return $definition['fields'] ?? (($definition['gradeOnly'] ?? false) ? ['grade'] : ['session', 'grade', 'section']);
    }

    public static function forReport(Request $request, array $schoolIds, string $report, array $definition, bool $allBranches): array
    {
        $today = today();
        $dates = $definition['dates'] ?? true;
        $staff = ($definition['module'] ?? null) === 'staff';
        $classKey = $allBranches ? 'title' : 'id';
        $classType = $allBranches ? 'string' : 'integer';

        return Validator::make([
            'report' => $report,
            ...($dates ? [
                'from' => $request->query('from') ?? $today->copy()->startOfMonth()->toDateString(),
                'to' => $request->query('to') ?? $today->toDateString(),
            ] : []),
            ...($report === 'low-attendance' ? ['threshold' => $request->query('threshold') ?? 75] : []),
            ...$request->only(self::fields($definition)),
        ], [
            'report' => ['required', 'string'],
            'threshold' => ['sometimes', 'integer', 'between:0,100'],
            'from' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'session' => ['nullable', 'string', $staff ? 'max:100' : 'max:20',
                Rule::exists($staff ? 'school_sessions' : 'enrollments', $staff ? 'title' : 'academic_year')->whereIn('school_id', $schoolIds)],
            'grade' => ['nullable', $classType, Rule::exists('grades', $classKey)->whereIn('school_id', $schoolIds)],
            'section' => ['nullable', $classType, Rule::exists('sections', $classKey)->whereIn('school_id', $schoolIds)],
        ])->validate();
    }
}
