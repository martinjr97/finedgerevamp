<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerHasSecurityQuestion
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if ($customer && ! $customer->hasSecurityQuestionConfigured()) {
            if (! $request->routeIs(
                'customer.security-questions.setup',
                'customer.security-questions.store',
                'customer.logout',
                'customer.pin.edit',
                'customer.pin.update',
                'customer.account.delete.store',
            )) {
                return redirect()
                    ->route('customer.security-questions.setup')
                    ->with('status', 'Please set a security question before continuing.');
            }
        }

        return $next($request);
    }
}
