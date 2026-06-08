<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\RepaymentRecoveryMethod;
use App\Models\Loan;
use App\Services\LoanRepaymentLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StatementController extends Controller
{
    public function __construct(
        private readonly LoanRepaymentLedgerService $ledgerService
    ) {}

    /**
     * Display the customer loan statement
     */
    public function index(Request $request): View
    {
        $customer = auth('customer')->user();
        
        // Get filter parameters
        $loanId = $request->input('loan_id');
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        // Get all loans for the customer
        $loansQuery = $customer->loans()->with(['loanProduct', 'customerGroup', 'accruals']);
        
        if ($loanId) {
            $loansQuery->where('id', $loanId);
        }

        $loans = $loansQuery->orderBy('loan_start_date', 'desc')->get();

        // Build transaction history for selected loan or all loans
        $transactions = $this->buildTransactionHistory($loans, $startDate, $endDate);

        // Calculate summary statistics
        $summary = $this->calculateSummary($customer, $loans);

        return view('customer.statement', [
            'loans' => $customer->loans()->with(['loanProduct', 'customerGroup'])->orderBy('loan_start_date', 'desc')->get(),
            'selectedLoan' => $loanId ? Loan::find($loanId) : null,
            'transactions' => $transactions,
            'summary' => $summary,
            'startDate' => $startDate?->format('Y-m-d'),
            'endDate' => $endDate?->format('Y-m-d'),
        ]);
    }

    /**
     * Build transaction history from accruals and payments
     */
    private function buildTransactionHistory($loans, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $transactions = [];

        foreach ($loans as $loan) {
            // Add loan creation/approval as initial transaction
            $loanCreatedDate = $loan->approved_at ? $loan->approved_at : $loan->created_at;
            
            if (!$startDate || $loanCreatedDate->gte($startDate)) {
                if (!$endDate || $loanCreatedDate->lte($endDate)) {
                    $transactions[] = [
                        'date' => $loanCreatedDate,
                        'type' => 'loan_created',
                        'loan' => $loan,
                        'description' => 'Loan Created',
                        'principal' => $loan->principal_amount,
                        'processing_fee' => $loan->processing_fee,
                        'interest' => 0,
                        'payment' => 0,
                        'balance' => $loan->principal_amount + $loan->processing_fee,
                        'outstanding_balance' => $loan->principal_amount + $loan->processing_fee,
                    ];
                }
            }

            // Add accruals
            foreach ($loan->accruals as $accrual) {
                $accrualDate = Carbon::parse($accrual->accrual_date);
                
                if ($startDate && $accrualDate->lt($startDate)) continue;
                if ($endDate && $accrualDate->gt($endDate)) continue;

                $transactions[] = [
                    'date' => $accrualDate,
                    'type' => 'accrual',
                    'loan' => $loan,
                    'description' => 'Interest Accrued (' . ucfirst($accrual->accrual_period) . ')',
                    'principal' => 0,
                    'processing_fee' => 0,
                    'interest' => $accrual->interest_amount,
                    'payment' => 0,
                    'balance' => $accrual->total_balance,
                    'outstanding_balance' => $accrual->total_balance,
                    'rate_used' => $accrual->rate_used,
                ];
            }

            $expectedSettlement = $this->ledgerService->getExpectedSettlementAmount($loan);
            $runningNetPaid = 0.0;

            foreach ($loan->loanRepayments()->with(['repayment', 'refundOf.repayment'])->orderBy('created_at')->orderBy('id')->get() as $loanRepayment) {
                $repayment = $loanRepayment->repayment;
                $repaymentDate = $repayment->processed_at ?? $repayment->created_at;
                
                if ($startDate && $repaymentDate->lt($startDate)) continue;
                if ($endDate && $repaymentDate->gt($endDate)) continue;

                $runningNetPaid = round($runningNetPaid + (float) $loanRepayment->amount, 2);
                $runningOutstanding = $this->ledgerService->calculateOutstandingBalance($loan, $runningNetPaid);
                $runningSuspense = $this->ledgerService->calculateSuspenseAmount($loan, $runningNetPaid);

                $isRefund = $loanRepayment->isRefund();
                $originalReference = $loanRepayment->refundOf?->repayment?->repayment_number;

                $statementNotes = $isRefund
                    ? ($loanRepayment->notes ?: null)
                    : $this->recoveryMethodStatementNote($repayment->recovery_method, $loanRepayment->notes);

                $transactions[] = [
                    'date' => $repaymentDate,
                    'type' => $isRefund ? 'refund' : 'payment',
                    'loan' => $loan,
                    'description' => $isRefund
                        ? 'Refund issued'.($originalReference ? ' (against '.$originalReference.')' : '').' - '.$repayment->repayment_number
                        : 'Payment Received - '.$repayment->repayment_number,
                    'notes' => $statementNotes,
                    'principal' => 0,
                    'processing_fee' => 0,
                    'interest' => 0,
                    'payment' => (float) $loanRepayment->amount,
                    'net_paid' => $runningNetPaid,
                    'suspense_amount' => $runningSuspense,
                    'expected_settlement' => $expectedSettlement,
                    'balance' => $runningOutstanding,
                    'outstanding_balance' => $runningOutstanding,
                    'repayment' => $repayment,
                    'loan_repayment' => $loanRepayment,
                ];
            }

            // Add loan settlement if settled
            if (in_array($loan->status, ['completed', 'settled']) && $loan->loan_settled_date) {
                $settledDate = Carbon::parse($loan->loan_settled_date);
                
                if (!$startDate || $settledDate->gte($startDate)) {
                    if (!$endDate || $settledDate->lte($endDate)) {
                        $transactions[] = [
                            'date' => $settledDate,
                            'type' => 'loan_settled',
                            'loan' => $loan,
                            'description' => 'Loan Fully Settled',
                            'principal' => 0,
                            'processing_fee' => 0,
                            'interest' => 0,
                            'payment' => 0,
                            'balance' => 0,
                            'outstanding_balance' => 0,
                        ];
                    }
                }
            }
        }

        // Sort transactions by date and convert to Collection
        usort($transactions, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return collect($transactions);
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummary($customer, $loans): array
    {
        $totalLoans = $loans->count();
        $activeLoans = $loans->whereIn('status', ['approved', 'active'])->count();
        $completedLoans = $loans->whereIn('status', ['completed', 'settled'])->count();
        
        $totalBorrowed = $loans->sum('principal_amount');
        $totalInterest = $loans->sum('interest_accrued');
        $totalPaid = $loans->sum('amount_paid');
        $totalOutstanding = $loans->sum('outstanding_balance');

        return [
            'total_loans' => $totalLoans,
            'active_loans' => $activeLoans,
            'completed_loans' => $completedLoans,
            'total_borrowed' => $totalBorrowed,
            'total_interest' => $totalInterest,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
        ];
    }

    private function recoveryMethodStatementNote(?string $recoveryMethod, ?string $loanRepaymentNotes): ?string
    {
        $recoveryLabel = RepaymentRecoveryMethod::label($recoveryMethod);
        $recoveryNote = $recoveryLabel !== RepaymentRecoveryMethod::label(RepaymentRecoveryMethod::NORMAL)
            ? 'Recovery method: '.$recoveryLabel
            : null;

        return trim(collect([$loanRepaymentNotes, $recoveryNote])->filter()->implode(' | ')) ?: null;
    }
}
