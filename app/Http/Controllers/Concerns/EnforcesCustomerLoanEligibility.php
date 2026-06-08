<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

trait EnforcesCustomerLoanEligibility
{
    /**
     * Company-backed MOU/SME flows may not assign a customer group; group concurrency rules do not apply.
     */
    protected function shouldEnforceGroupLoanEligibility(Customer $customer, ?LoanProduct $loanProduct = null): bool
    {
        $category = $loanProduct?->category ?? $customer->loanProduct?->category;

        if (in_array($category, ['mou', 'sme'], true) && ! $customer->customerGroup) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the borrowing customer (e.g. SME company for a representative).
     */
    protected function resolveLoanApplicationBorrower(Customer $customer, LoanProduct $loanProduct): Customer
    {
        if ($loanProduct->category === 'sme' && $customer->customer_type === 'representative') {
            return $customer->parentCustomer ?? $customer;
        }

        return $customer;
    }

    protected function loanEligibilityRedirect(Customer $borrower, LoanProduct $loanProduct): ?RedirectResponse
    {
        if (! $this->shouldEnforceGroupLoanEligibility($borrower, $loanProduct)) {
            return null;
        }

        if ($borrower->canTakeAnotherLoan()) {
            return null;
        }

        return redirect()
            ->route('admin.loan-applications.search-customer', $loanProduct)
            ->with('error', $borrower->loanEligibilityBlockingMessage());
    }

    protected function loanEligibilityJsonError(Customer $borrower, ?LoanProduct $loanProduct = null): ?JsonResponse
    {
        if (! $this->shouldEnforceGroupLoanEligibility($borrower, $loanProduct)) {
            return null;
        }

        if ($borrower->canTakeAnotherLoan()) {
            return null;
        }

        return response()->json([
            'error' => $borrower->loanEligibilityBlockingMessage(),
        ], 422);
    }

    /**
     * Customer portal redirect when the borrower cannot start or continue a loan application.
     */
    protected function customerPortalLoanEligibilityRedirect(Customer $customer): ?RedirectResponse
    {
        if (! $this->shouldEnforceGroupLoanEligibility($customer)) {
            return null;
        }

        if ($customer->canTakeAnotherLoan()) {
            return null;
        }

        return redirect()
            ->route('customer.dashboard')
            ->with('error', $customer->loanEligibilityBlockingMessage());
    }

    /**
     * Customer portal JSON error for AJAX loan application steps.
     */
    protected function customerPortalLoanEligibilityJsonError(Customer $customer): ?JsonResponse
    {
        if (! $this->shouldEnforceGroupLoanEligibility($customer)) {
            return null;
        }

        if ($customer->canTakeAnotherLoan()) {
            return null;
        }

        return response()->json([
            'error' => $customer->loanEligibilityBlockingMessage(),
        ], 422);
    }
}
