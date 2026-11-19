<?php

namespace App\Http\Middleware;

use App\Services\BranchModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireBranchModule
{
    public function __construct(private readonly BranchModules $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $this->modules->assertEnabled((int) $request->route('school'), $module);

        return $next($request);
    }
}
