<?php

namespace App\Http\Middleware;

use App\Services\BranchAuditLog;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareAdminAuditAccess
{
    public function __construct(private readonly BranchAuditLog $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/*')) {
            Inertia::share('canViewAuditLog', fn () => $this->audit->canView($request->user()));
        }

        return $next($request);
    }
}
