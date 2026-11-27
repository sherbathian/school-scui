<?php

namespace App\Http\Middleware;

use App\Services\BranchAuditLog;
use App\Services\SchoolWebsiteAccess;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareAdminAuditAccess
{
    public function __construct(private readonly BranchAuditLog $audit, private readonly SchoolWebsiteAccess $websites) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*') || $request->route()?->getDomain() === 'admin.taleemfinder.pk') {
            Inertia::share('canManageSchoolWebsite', fn () => $this->websites->branchIds($request->user())->isNotEmpty());
            Inertia::share('canViewAuditLog', fn () => $this->audit->canView($request->user()));
        }

        return $next($request);
    }
}
