<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerCanRequestSelfServiceLoan
{
    /**
     * Block self-service loan requests for customers on group loan products.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        if (! $customer) {
            return $next($request);
        }

        $customer->loadMissing('loanProduct:id,category');
        if ((string) ($customer->loanProduct?->category ?? '') === 'group_loans') {
            return redirect()
                ->route('customer.dashboard')
                ->with('error', 'Loan requests for Group Loans customers are managed by your relationship manager. Please contact your relationship manager to apply.');
        }

        return $next($request);
    }
}
