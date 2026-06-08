<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get customer dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        $customer->load(['loanProduct', 'customerGroup', 'loans', 'company']);

        // Get dashboard data
        $activeLoans = $customer->activeLoans();
        $totalOutstandingBalance = $customer->getTotalOutstandingBalance();
        $availableLoanAmount = $customer->getAvailableLoanAmount();
        $nextPaymentDate = $customer->getNextPaymentDate();
        $canTakeAnotherLoan = $customer->canTakeAnotherLoan();
        $loanEligibilityBlockingMessage = $canTakeAnotherLoan ? null : $customer->loanEligibilityBlockingMessage();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => new \App\Http\Resources\Api\V1\CustomerResource($customer),
                'dashboard' => [
                    'active_loans_count' => $activeLoans->count(),
                    'total_outstanding_balance' => $totalOutstandingBalance,
                    'available_loan_amount' => $availableLoanAmount,
                    'maximum_loan_take' => $customer->maximum_loan_take ?? 0,
                    'next_payment_date' => $nextPaymentDate?->format('Y-m-d'),
                    'next_payment_date_human' => $nextPaymentDate?->diffForHumans(),
                    'can_take_another_loan' => $canTakeAnotherLoan,
                    'loan_eligibility_blocking_message' => $loanEligibilityBlockingMessage,
                    'active_loans' => \App\Http\Resources\Api\V1\LoanResource::collection($activeLoans),
                ],
            ],
        ]);
    }
}

