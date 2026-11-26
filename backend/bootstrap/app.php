<?php

use App\Http\Middleware\ConfigureProductionPortals;
use App\Http\Middleware\ShareAdminAuditAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(ConfigureProductionPortals::class);
        $middleware->statefulApi();
        $middleware->web(append: [ShareAdminAuditAccess::class]);
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*')) {
                return null;
            }

            return route(match (true) {
                $request->route()?->getDomain() === 'admin.taleemfinder.pk' => 'management.admin.login',
                $request->is('admin/*') => 'admin.login',
                default => 'login',
            });
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
