<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the customer dashboard
     */
    public function index(): View
    {
        $customer = auth('customer')->user();
        $customer->load(['loanProduct', 'customerGroup.relationshipManager', 'loans']);

        $pendingReviewLoan = $customer->loans()
            ->reorder()
            ->with([
                'loanProduct:id,name',
                'channel:id,name',
            ])
            ->where('status', 'pending_approval')
            ->latest('created_at')
            ->first();

        // Get active loans
        $activeLoans = $customer->activeLoans();
        $primaryActiveLoan = $activeLoans->first();
        $totalOutstandingBalance = $customer->getTotalOutstandingBalance();
        $projectedRepaymentTotal = $activeLoans->sum(fn ($loan) => $loan->getProjectedTotalAmount());
        $availableLoanAmount = $customer->getAvailableLoanAmount();
        $nextPaymentDate = $customer->getNextPaymentDate();
        $canTakeAnotherLoan = $customer->canTakeAnotherLoan();
        $loanEligibilityBlockingMessage = $canTakeAnotherLoan ? null : $customer->loanEligibilityBlockingMessage();
        $isGroupLoanCustomer = (string) ($customer->loanProduct?->category ?? '') === 'group_loans';
        $hasBlockingLoans = $customer->loans()
            ->whereIn('status', ['approved', 'active', 'pending_approval'])
            ->exists();
        $canStartLoanFlow = ! $isGroupLoanCustomer
            && $availableLoanAmount > 0
            && ($canTakeAnotherLoan || ! $hasBlockingLoans);

        return view('customer.dashboard', [
            'customer' => $customer,
            'activeLoans' => $activeLoans,
            'totalOutstandingBalance' => $totalOutstandingBalance,
            'projectedRepaymentTotal' => $projectedRepaymentTotal,
            'primaryActiveLoan' => $primaryActiveLoan,
            'availableLoanAmount' => $availableLoanAmount,
            'nextPaymentDate' => $nextPaymentDate,
            'canTakeAnotherLoan' => $canTakeAnotherLoan,
            'loanEligibilityBlockingMessage' => $loanEligibilityBlockingMessage,
            'canStartLoanFlow' => $canStartLoanFlow,
            'isGroupLoanCustomer' => $isGroupLoanCustomer,
            'maximumLoanTake' => $customer->maximum_loan_take ?? 0,
            'pendingReviewLoan' => $pendingReviewLoan,
        ]);
    }
}
