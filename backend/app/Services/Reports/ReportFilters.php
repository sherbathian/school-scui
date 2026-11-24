<?php

namespace App\Services\Reports;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ReportFilters
{
    public static function attendance(Request $request, School $school): array
    {
        $today = today();
        $report = $request->query('report', 'daily');

        return Validator::make([
            'report' => $report,
            'from' => $request->query('from') ?? $today->copy()->startOfMonth()->toDateString(),
            'to' => $request->query('to') ?? $today->toDateString(),
            ...($report === 'low-attendance' ? ['threshold' => $request->query('threshold') ?? 75] : []),
            ...$request->only(['session', 'grade', 'section']),
        ], [
            'report' => ['required', 'string', Rule::in(array_keys(AttendanceReports::CATALOGUE))],
            'threshold' => ['sometimes', 'integer', 'between:0,100'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'session' => ['nullable', 'string', 'max:20', Rule::exists('enrollments', 'academic_year')->where('school_id', $school->id)],
            'grade' => ['nullable', 'integer', Rule::exists('grades', 'id')->where('school_id', $school->id)],
            'section' => ['nullable', 'integer', Rule::exists('sections', 'id')->where('school_id', $school->id)],
        ])->validate();
    }
}
