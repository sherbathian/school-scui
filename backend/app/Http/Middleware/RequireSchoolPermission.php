<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSchoolPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->route('school')) {
            return $next($request);
        }

        $schoolId = (int) $request->route('school');
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $school = $user->activeSchools()->whereKey($schoolId)->first();
        abort_unless($school, 404);

        $permission = $this->requiredPermission($request);
        $allowed = is_array($permission)
            ? collect($permission)->contains(fn (string $name) => $user->hasSchoolPermission($school, $name))
            : $user->hasSchoolPermission($school, $permission);
        abort_unless($allowed, 403, 'You do not have permission to perform this action for the selected school.');

        return $next($request);
    }

    private function requiredPermission(Request $request): string|array
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);
        $schoolIndex = array_search('schools', $segments, true);
        $resource = $segments[($schoolIndex === false ? 1 : $schoolIndex + 2)] ?? '';
        $method = strtoupper($request->method());
        $action = in_array($method, ['GET', 'HEAD'], true) ? 'view' : 'manage';

        if ($resource === 'schools') {
            return $action === 'view' ? 'schools.view' : 'schools.manage';
        }

        $module = match ($resource) {
            'users' => 'users',
            'roles' => 'roles',
            'students' => 'students',
            'student-inquiries' => 'inquiries',
            'guardians' => 'guardians',
            'attendance' => 'attendance',
            'grades', 'sections', 'subjects', 'sessions' => 'academics',
            'staff', 'staff-lookups' => 'staff',
            'fee-types', 'legacy-fee-types', 'fee-discounts', 'fee-groups', 'fee-masters', 'fee-invoices' => 'fees',
            'expenses', 'expense-heads' => 'expenses',
            default => 'schools',
        };

        if ($module === 'users') {
            return 'users.manage';
        }
        if ($module === 'roles') {
            if (in_array($method, ['GET', 'HEAD'], true)) {
                return ['roles.manage', 'users.manage'];
            }

            return "{$module}.manage";
        }

        if ($module === 'attendance' && $action === 'manage' && $method !== 'PUT') {
            $action = 'view';
        }

        return "{$module}.{$action}";
    }
}
