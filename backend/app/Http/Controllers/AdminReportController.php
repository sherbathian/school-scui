<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\BranchModules;
use App\Services\Reports\AttendanceReports;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\StudentReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class AdminReportController
{
    public function __invoke(Request $request, BranchModules $modules, AttendanceReports $attendance, StudentReports $students, ?int $school = null): Response
    {
        $user = $request->user();
        abort_unless($user->canEnterSchoolhouse(), 403);
        $platform = Gate::forUser($user)->allows('moderateListings', School::class);
        $print = $request->routeIs('admin.reports.print');
        $csv = $request->routeIs('admin.reports.csv');
        $schools = ($platform ? School::query() : $user->activeSchools())
            ->when($print || $csv, fn ($query) => $query->whereKey($school))
            ->orderBy('name')->get();
        $available = $schools->filter(function (School $branch) use ($user, $platform, $modules): bool {
            $role = $platform ? null : $user->schoolAccessRole($branch);
            if (! $platform && (! in_array($role, ['admin', 'school_admin', 'account', 'accountant', 'owner', 'group_admin', 'group_viewer'], true)
                || ! $user->hasSchoolPermission($branch, 'reports.view'))) {
                return false;
            }
            $reports = $modules->forBranch($branch->id)->firstWhere('slug', 'reports');
            if (! ($reports['enabled'] ?? false)) {
                return false;
            }
            // Reports is paid. A legacy branch needs an explicit platform switch.
            return $reports['has_override'] || ($branch->group_id !== null
                && DB::table('school_subscriptions')->where('school_group_id', $branch->group_id)->exists());
        });

        $branch = $school === null ? $available->first() : $available->find($school);
        abort_unless($branch, 403);
        $catalogue = AttendanceReports::CATALOGUE + StudentReports::CATALOGUE;
        $enabled = $modules->forBranch($branch->id)->where('enabled', true)->pluck('slug')->all();
        $permissions = $platform ? [] : $user->permissionsForSchool($branch);
        $options = array_filter($catalogue, function (array $definition) use ($enabled, $permissions, $platform): bool {
            $source = $definition['module'] ?? 'attendance';
            $permission = $source === 'inquiries' ? 'students.view' : $source.'.view';

            return in_array($source, $enabled, true) && ($platform || in_array($permission, $permissions, true));
        });
        $requested = $request->query('report');
        if ($requested !== null && (! is_string($requested) || ! isset($catalogue[$requested]))) {
            throw ValidationException::withMessages(['report' => 'Select a valid report.']);
        }
        $report = $requested ?? (($csv || $print) ? 'daily' : (array_key_first($options) ?? 'daily'));
        $definition = $catalogue[$report];
        $canViewReport = isset($options[$report]);
        abort_if(! $canViewReport && ($print || $csv || $request->hasAny(['report', 'threshold', 'from', 'to', 'session', 'grade', 'section'])), 403);

        $filters = $canViewReport ? ReportFilters::forReport($request, $branch, $report, $definition) : [];
        $provider = isset($definition['module']) ? $students : $attendance;
        $rows = $canViewReport ? $provider->rows($report, $branch, $filters) : [];

        if ($csv) {
            return response()->streamDownload(function () use ($rows, $definition): void {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, $definition['columns'], escape: '');
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^\\s*[=+@-]|^[\\t\\r\\n]/', $value)
                        ? "'".$value : $value, $row), escape: '');
                }
                fclose($stream);
            }, $definition['filename'], ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        Inertia::setRootView('admin.app');

        return Inertia::render('Admin/Reports', [
            'adminName' => $user->name,
            'platform' => $platform,
            'branch' => $branch->only('id', 'name'),
            'branches' => $available->values()->map->only('id', 'name'),
            'canViewAttendance' => isset($options['daily']),
            'canViewReport' => $canViewReport,
            'filters' => $filters,
            'report' => $report,
            'title' => $definition['title'],
            'reportOptions' => array_map(fn (array $option) => [
                'title' => $option['title'], 'dates' => $option['dates'] ?? true, 'gradeOnly' => $option['gradeOnly'] ?? false,
            ], $options),
            'columns' => $definition['columns'],
            'rows' => $rows,
            'print' => $print,
            'sessions' => $canViewReport && ! $print && ! ($definition['gradeOnly'] ?? false) ? DB::table('enrollments')->where('school_id', $branch->id)
                ->distinct()->orderByDesc('academic_year')->pluck('academic_year') : [],
            'grades' => $canViewReport && ! $print ? DB::table('grades')->where('school_id', $branch->id)->orderBy('title')->get(['id', 'title']) : [],
            'sections' => $canViewReport && ! $print && ! ($definition['gradeOnly'] ?? false) ? DB::table('sections')->where('school_id', $branch->id)->orderBy('title')->get(['id', 'title']) : [],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
