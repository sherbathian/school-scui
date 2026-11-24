<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\BranchModules;
use App\Services\Reports\AttendanceReports;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class AdminReportController
{
    public function __invoke(Request $request, BranchModules $modules, AttendanceReports $reports, ?int $school = null): Response
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
        $canViewAttendance = ($platform || $user->hasSchoolPermission($branch, 'attendance.view'))
            && ($modules->forBranch($branch->id)->firstWhere('slug', 'attendance')['enabled'] ?? false);
        abort_if(! $canViewAttendance && ($print || $csv || $request->hasAny(['from', 'to', 'session', 'grade', 'section'])), 403);

        $filters = $canViewAttendance ? ReportFilters::attendance($request, $branch) : [];
        $rows = $canViewAttendance ? $reports->daily($branch, $filters) : [];

        if ($csv) {
            return response()->streamDownload(function () use ($rows): void {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, AttendanceReports::DAILY_COLUMNS, escape: '');
                foreach ($rows as $row) {
                    fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^\\s*[=+@-]|^[\\t\\r\\n]/', $value)
                        ? "'".$value : $value, $row), escape: '');
                }
                fclose($stream);
            }, 'attendance.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        Inertia::setRootView('admin.app');

        return Inertia::render('Admin/Reports', [
            'adminName' => $user->name,
            'platform' => $platform,
            'branch' => $branch->only('id', 'name'),
            'branches' => $available->values()->map->only('id', 'name'),
            'canViewAttendance' => $canViewAttendance,
            'filters' => $filters,
            'columns' => AttendanceReports::DAILY_COLUMNS,
            'rows' => $rows,
            'print' => $print,
            'sessions' => $canViewAttendance && ! $print ? DB::table('enrollments')->where('school_id', $branch->id)
                ->distinct()->orderByDesc('academic_year')->pluck('academic_year') : [],
            'grades' => $canViewAttendance && ! $print ? DB::table('grades')->where('school_id', $branch->id)->orderBy('title')->get(['id', 'title']) : [],
            'sections' => $canViewAttendance && ! $print ? DB::table('sections')->where('school_id', $branch->id)->orderBy('title')->get(['id', 'title']) : [],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }
}
