<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSchoolPermissionTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->is_active === false, 403, 'This account is inactive.');

        setPermissionsTeamId($request->route('school') ?: 0);
        $request->user()?->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $next($request);
        } finally {
            setPermissionsTeamId(0);
            $request->user()?->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
