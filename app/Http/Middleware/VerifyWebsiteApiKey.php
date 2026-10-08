<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebsiteApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('website.api_key');

        if (! is_string($configured) || $configured === '') {
            return response()->json([
                'message' => 'Public website integration is not configured.',
            ], 503);
        }

        $provided = $request->header('X-Finedge-Website-Key', '');

        if (! hash_equals($configured, (string) $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
