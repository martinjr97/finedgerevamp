<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyMigrationDashboardEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('migration-dashboard.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
