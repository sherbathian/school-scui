<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ConfigureProductionPortals
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        if (in_array($host, ['taleemfinder.pk', 'admin.taleemfinder.pk'], true)) {
            URL::forceScheme('https');
        }

        if ($host !== 'taleemfinder.pk') {
            return $next($request);
        }

        abort_if($request->is('api/admin', 'api/admin/*'), 404);

        if ($request->is('admin', 'admin/*')) {
            abort_unless($request->isMethodSafe(), 404);

            return redirect()->away('https://admin.taleemfinder.pk'.$request->getRequestUri());
        }

        return $next($request);
    }
}
