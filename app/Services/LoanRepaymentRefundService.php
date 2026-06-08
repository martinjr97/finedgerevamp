<?php

namespace App\Services;

use App\Support\RepaymentRecoveryMethod;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LoanRepaymentRefundService
{
    public function __construct(
        private readonly LoanRepaymentLedgerService $ledgerService
    ) {}

    public function applyRefund(Loan $loan, LoanRepayment $originalLoanRepayment, float $refundAmount, string $reason, ?int $adminId = null): LoanRepayment
    {
        if ($refundAmount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if (! $originalLoanRepayment->isPayment() || (float) $originalLoanRepayment->amount <= 0) {
            throw new InvalidArgumentException('Only positive payment records can be refunded.');
        }

        if ((int) $originalLoanRepayment->loan_id !== (int) $loan->id) {
            throw new InvalidArgumentException('The selected repayment does not belong to this loan.');
        }

        $refundableRemaining = $originalLoanRepayment->refundableAmountRemaining();
        if ($refundAmount > $refundableRemaining + 0.0001) {
            throw new InvalidArgumentException(
                'Refund amount cannot exceed the remaining refundable amount of ZMW '.number_format($refundableRemaining, 2).'.'
            );
        }

        return DB::transaction(function () use ($loan, $originalLoanRepayment, $refundAmount, $reason, $adminId): LoanRepayment {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $originalLoanRepayment = LoanRepayment::query()
                ->lockForUpdate()
                ->findOrFail($originalLoanRepayment->id);

            $refundableRemaining = $originalLoanRepayment->refundableAmountRemaining();
            if ($refundAmount > $refundableRemaining + 0.0001) {
                throw new InvalidArgumentException(
                    'Refund amount cannot exceed the remaining refundable amount of ZMW '.number_format($refundableRemaining, 2).'.'
                );
            }

            $originalRepayment = $originalLoanRepayment->repayment;
            if (! $originalRepayment) {
                throw new InvalidArgumentException('The original repayment record is missing.');
            }

            $netPaidBefore = $this->ledgerService->calculateNetPaid($loan);
            $outstandingBalanceBefore = $this->ledgerService->calculateOutstandingBalance($loan, $netPaidBefore);
            $componentSplit = $originalLoanRepayment->calculateRefundComponentSplit($refundAmount);

            $scheduleReversalAmount = $this->ledgerService->scheduleReversalAmountForRefund(
                $loan,
                $refundAmount,
                $netPaidBefore
            );

            if ($loan->paymentSchedules()->exists() && $scheduleReversalAmount > 0) {
                $loan->reversePaymentSchedule($scheduleReversalAmount);
            }

            $refundRepayment = Repayment::create([
                'customer_id' => $loan->customer_id,
                'channel_id' => $originalRepayment->channel_id ?? $loan->channel_id,
                'repayment_number' => Repayment::generateRepaymentNumber(),
                'total_amount' => round(-$refundAmount, 2),
                'recovery_method' => $originalRepayment->recovery_method ?? RepaymentRecoveryMethod::NORMAL,
                'phone_number' => $originalRepayment->phone_number,
                'status' => 'completed',
                'processed_at' => now(),
                'metadata' => array_merge($originalRepayment->metadata ?? [], [
                    'transaction_type' => LoanRepayment::TRANSACTION_TYPE_REFUND,
                    'refund_of_repayment_id' => $originalRepayment->id,
                    'refund_of_loan_repayment_id' => $originalLoanRepayment->id,
                    'refund_reason' => $reason,
                    'refunded_by_admin_id' => $adminId,
                ]),
            ]);

            $netPaidAfter = round($netPaidBefore - $refundAmount, 2);
            $outstandingBalanceAfter = $this->ledgerService->calculateOutstandingBalance($loan, $netPaidAfter);

            $refundLoanRepayment = LoanRepayment::create([
                'repayment_id' => $refundRepayment->id,
                'loan_id' => $loan->id,
                'transaction_type' => LoanRepayment::TRANSACTION_TYPE_REFUND,
                'refund_of_loan_repayment_id' => $originalLoanRepayment->id,
                'amount' => round(-$refundAmount, 2),
                'principal_amount' => round(-$componentSplit['principal_amount'], 2),
                'interest_amount' => round(-$componentSplit['interest_amount'], 2),
                'processing_fee_amount' => round(-$componentSplit['processing_fee_amount'], 2),
                'outstanding_balance_before' => $outstandingBalanceBefore,
                'outstanding_balance_after' => $outstandingBalanceAfter,
                'notes' => $reason,
                'metadata' => [
                    'refund_reason' => $reason,
                    'refunded_by_admin_id' => $adminId,
                    'original_repayment_number' => $originalRepayment->repayment_number,
                    'schedule_amount_reversed' => $scheduleReversalAmount,
                    'suspense_amount_reduced' => round(min(
                        $refundAmount,
                        $this->ledgerService->calculateSuspenseAmount($loan, $netPaidBefore)
                    ), 2),
                ],
            ]);

            $this->ledgerService->syncLoanLedger($loan->fresh());
            $this->refreshCustomerCreditScore($loan);

            return $refundLoanRepayment;
        });
    }

    private function refreshCustomerCreditScore(Loan $loan): void
    {
        try {
            $loan->loadMissing('customer');
            if ($loan->customer) {
                \App\Support\CreditScoreService::updateCreditScore($loan->customer);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update credit score after loan refund', [
                'loan_id' => $loan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
