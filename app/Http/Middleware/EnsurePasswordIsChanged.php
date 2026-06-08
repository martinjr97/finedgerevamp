<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin && $admin->must_change_password) {
            if (! $request->routeIs('admin.password.edit', 'admin.password.update', 'admin.logout')) {
                return redirect()->route('admin.password.edit');
            }
        }

        return $next($request);
    }
}

