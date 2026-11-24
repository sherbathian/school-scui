<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\BranchModules;
use App\Services\Reports\AttendanceReports;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\StaffReports;
use App\Services\Reports\StudentReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class AdminReportController
{
    public function __invoke(Request $request, BranchModules $modules, AttendanceReports $attendance, StudentReports $students, StaffReports $staff, ?int $school = null): Response
    {
        $user = $request->user();
        abort_unless($user->canEnterSchoolhouse(), 403);
        $platform = Gate::forUser($user)->allows('moderateListings', School::class);
        $print = $request->routeIs('admin.reports.print', 'admin.reports.all.print');
        $csv = $request->routeIs('admin.reports.csv', 'admin.reports.all.csv');
        $allBranches = $request->routeIs('admin.reports.all*');
        $schoolQuery = $platform ? School::query() : $user->activeSchools();
        $group = $school === null ? null : (clone $schoolQuery)->whereKey($school)->value('group_id');
        $comparisonGroups = $platform ? [] : DB::table('school_group_user')
            ->where('user_id', $user->id)->where('is_active', true)
            ->whereIn('role', ['owner', 'group_admin', 'group_viewer'])->pluck('school_group_id')->map(fn ($id) => (int) $id)->all();
        abort_if($allBranches && ($group === null || ! in_array((int) $group, $comparisonGroups, true)), 403);

        $schools = $schoolQuery
            ->when($allBranches, fn ($query) => $query->where('group_id', $group))
            ->when(! $allBranches && ($print || $csv), fn ($query) => $query->whereKey($school))
            ->orderBy('name')->orderBy('id')->get();
        $branchModules = [];
        $available = $schools->filter(function (School $branch) use ($user, $platform, $modules, &$branchModules): bool {
            $role = $platform ? null : $user->schoolAccessRole($branch);
            if (! $platform && (! in_array($role, ['admin', 'school_admin', 'account', 'accountant', 'owner', 'group_admin', 'group_viewer'], true)
                || ! $user->hasSchoolPermission($branch, 'reports.view'))) {
                return false;
            }
            $branchModules[$branch->id] = $modules->forBranch($branch->id);
            $reports = $branchModules[$branch->id]->firstWhere('slug', 'reports');
            if (! ($reports['enabled'] ?? false)) {
                return false;
            }
            // Reports is paid. A legacy branch needs an explicit platform switch.
            return $reports['has_override'] || ($branch->group_id !== null
                && DB::table('school_subscriptions')->where('school_group_id', $branch->group_id)->exists());
        });

        $branch = $school === null ? $available->first() : $available->find($school);
        abort_unless($branch, 403);
        $canCompare = $branch->group_id !== null && in_array((int) $branch->group_id, $comparisonGroups, true);
        $catalogue = AttendanceReports::CATALOGUE + StudentReports::CATALOGUE + StaffReports::CATALOGUE;
        $candidates = $allBranches ? $available : collect([$branch]);
        $access = $candidates->mapWithKeys(function (School $candidate) use ($branchModules, $platform, $user, $catalogue): array {
            $enabled = $branchModules[$candidate->id]->where('enabled', true)->pluck('slug')->all();
            $permissions = $platform ? [] : $user->permissionsForSchool($candidate);
            $options = array_filter($catalogue, function (array $definition) use ($enabled, $permissions, $platform): bool {
                $source = $definition['module'] ?? 'attendance';
                $permission = $source === 'inquiries' ? 'students.view' : $source.'.view';

                return in_array($source, $enabled, true) && ($platform || in_array($permission, $permissions, true));
            });

            return [$candidate->id => array_keys($options)];
        });
        $options = array_intersect_key($catalogue, array_flip($access->flatten()->unique()->all()));
        $requested = $request->query('report');
        if ($requested !== null && (! is_string($requested) || ! isset($catalogue[$requested]))) {
            throw ValidationException::withMessages(['report' => 'Select a valid report.']);
        }
        $report = $requested ?? ((! $allBranches && ($csv || $print)) ? 'daily' : (array_key_first($options) ?? 'daily'));
        $definition = $options[$report] ?? $catalogue[$report];
        $canViewReport = isset($options[$report]);
        abort_if(! $canViewReport && ($print || $csv || $request->hasAny(['report', 'threshold', 'from', 'to', 'session', 'grade', 'section'])), 403);

        $included = $candidates->filter(fn (School $candidate) => in_array($report, $access[$candidate->id], true));
        $ids = $included->pluck('id')->all();
        $filters = $canViewReport ? ReportFilters::forReport($request, $ids, $report, $definition, $allBranches) : [];
        $provider = match ($definition['module'] ?? 'attendance') {
            'staff' => $staff,
            'students', 'inquiries' => $students,
            'attendance' => $attendance,
        };
        $classIds = [];
        if ($allBranches) {
            foreach (['grade' => 'grades', 'section' => 'sections'] as $field => $table) {
                if (isset($filters[$field])) {
                    $classIds[$field] = DB::table($table)->whereIn('school_id', $ids)
                        ->where('title', $filters[$field])->pluck('id', 'school_id');
                }
            }
        }
        $rows = $included->flatMap(function (School $candidate) use ($allBranches, $provider, $report, $filters, $classIds): array {
            $branchFilters = $filters;
            foreach ($classIds as $field => $idsByBranch) {
                if (! isset($idsByBranch[$candidate->id])) {
                    return [];
                }
                $branchFilters[$field] = $idsByBranch[$candidate->id];
            }
            $rows = $provider->rows($report, $candidate, $branchFilters);

            return $allBranches ? array_map(fn (array $row) => [$candidate->name, ...$row], $rows) : $rows;
        })->all();
        if ($allBranches) {
            $definition['columns'] = ['Branch', ...$definition['columns']];
            $definition['filename'] = 'organisation-'.$definition['filename'];
        }

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
        $fields = $canViewReport && ! $print ? ReportFilters::fields($definition) : [];
        $sessions = [];
        if (in_array('session', $fields, true)) {
            $sessions = $provider === $staff
                ? DB::table('school_sessions')->whereIn('school_id', $ids)->orderByDesc('starts_on')->pluck('title')->unique()->values()
                : DB::table('enrollments')->whereIn('school_id', $ids)->distinct()->orderByDesc('academic_year')->pluck('academic_year');
        }

        return Inertia::render('Admin/Reports', [
            'adminName' => $user->name,
            'platform' => $platform,
            'branch' => $branch->only('id', 'name'),
            'branches' => $available->values()->map->only('id', 'name'),
            'allBranches' => $allBranches,
            'canCompare' => $canCompare,
            'organisationName' => $allBranches ? DB::table('school_groups')->where('id', $group)->value('name') : null,
            'canViewAttendance' => isset($options['daily']),
            'canViewReport' => $canViewReport,
            'filters' => $filters,
            'report' => $report,
            'title' => $definition['title'],
            'reportOptions' => array_map(fn (array $option) => [
                'title' => $option['title'], 'dates' => $option['dates'] ?? true, 'fields' => ReportFilters::fields($option),
            ], $options),
            'columns' => $definition['columns'],
            'rows' => $rows,
            'print' => $print,
            'sessions' => $sessions,
            'grades' => in_array('grade', $fields, true) ? $this->classOptions('grades', $ids, $allBranches) : [],
            'sections' => in_array('section', $fields, true) ? $this->classOptions('sections', $ids, $allBranches) : [],
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    private function classOptions(string $table, array $schoolIds, bool $allBranches): array
    {
        $query = DB::table($table)->whereIn('school_id', $schoolIds)->orderBy('title');

        return $allBranches
            ? $query->distinct()->pluck('title')->map(fn (string $title) => ['id' => $title, 'title' => $title])->all()
            : $query->get(['id', 'title'])->all();
    }
}
