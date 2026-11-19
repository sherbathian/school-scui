<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RestrictPersonalAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            abort_unless($request->routeIs('api.me') && in_array($request->method(), ['GET', 'HEAD'], true)
                && in_array('profile:read', $token->abilities ?? [], true), 403);
        }

        return $next($request);
    }
}
