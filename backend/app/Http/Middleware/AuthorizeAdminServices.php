<?php

namespace App\Http\Middleware;

use App\Models\School;
use App\Services\SchoolWebsiteAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeAdminServices
{
    public function __construct(private readonly SchoolWebsiteAccess $websites) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resource = $request->route('resource');
        if (in_array($resource, SchoolWebsiteAccess::RESOURCES, true)) {
            abort_unless(Gate::allows('moderateListings', School::class) || $this->websites->branchIds($request->user())->isNotEmpty(), 403);
        } else {
            Gate::authorize('moderateListings', School::class);
        }

        return $next($request);
    }
}
