<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Admin;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanRepayment;
use App\Models\PaymentGatewayAttempt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLoanRepaymentService
{
    public function __construct(
        private readonly EmployeeLoanLedgerService $ledgerService,
        private readonly EmployeeLoanScheduleService $scheduleService,
        private readonly EmployeeLoanFinancePostingService $financePostingService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function recordRepayment(EmployeeLoan $loan, array $input, Admin $processor): EmployeeLoanRepayment
    {
        $this->assertLoanAcceptsRepayment($loan);

        $amount = round((float) $input['amount'], 2);
        $this->assertAmountWithinOutstanding($loan, $amount);

        $allocation = $this->ledgerService->calculateRepaymentAllocation($loan, $amount);
        $effectiveDate = Carbon::parse($input['effective_date'])->toDateString();

        return DB::transaction(function () use ($loan, $input, $processor, $amount, $allocation, $effectiveDate) {
            $repayment = EmployeeLoanRepayment::create([
                'employee_loan_id' => $loan->id,
                'amount' => $amount,
                'principal_amount' => $allocation['principal_amount'],
                'interest_amount' => $allocation['interest_amount'],
                'processing_fee_amount' => $allocation['processing_fee_amount'],
                'arrears_interest_amount' => $allocation['arrears_interest_amount'],
                'effective_date' => $effectiveDate,
                'processed_at' => now(),
                'payment_method' => $input['payment_method'] ?? null,
                'repayment_source' => $input['repayment_source'] ?? EmployeeLoanRepayment::SOURCE_MANUAL,
                'reference' => $input['reference'] ?? null,
                'processed_by' => $processor->id,
                'status' => 'completed',
                'notes' => $input['notes'] ?? null,
                'metadata' => $input['metadata'] ?? null,
            ]);

            $this->applyRepaymentToLoan($loan, $repayment, $allocation);

            if (! empty($input['received_via_type']) && ! empty($input['received_via_id'])) {
                $this->financePostingService->postRepayment(
                    $repayment->fresh(),
                    (string) $input['received_via_type'],
                    (int) $input['received_via_id']
                );
            }

            return $repayment->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createPendingGatewayRepayment(EmployeeLoan $loan, array $input, Admin $processor): EmployeeLoanRepayment
    {
        $this->assertLoanAcceptsRepayment($loan);

        $amount = round((float) $input['amount'], 2);
        $this->assertAmountWithinOutstanding($loan, $amount);

        $allocation = $this->ledgerService->calculateRepaymentAllocation($loan, $amount);
        $effectiveDate = Carbon::parse($input['effective_date'])->toDateString();

        return EmployeeLoanRepayment::create([
            'employee_loan_id' => $loan->id,
            'amount' => $amount,
            'principal_amount' => $allocation['principal_amount'],
            'interest_amount' => $allocation['interest_amount'],
            'processing_fee_amount' => $allocation['processing_fee_amount'],
            'arrears_interest_amount' => $allocation['arrears_interest_amount'],
            'effective_date' => $effectiveDate,
            'payment_method' => $input['payment_method'] ?? 'mobile_money',
            'repayment_source' => EmployeeLoanRepayment::SOURCE_BANK,
            'reference' => $input['reference'] ?? null,
            'processed_by' => $processor->id,
            'status' => 'pending',
            'notes' => $input['notes'] ?? null,
            'metadata' => array_merge($input['metadata'] ?? [], [
                'collection_mode' => 'gateway',
                'gateway_phone' => $input['phone'] ?? null,
            ]),
        ]);
    }

    public function completeGatewayRepayment(
        EmployeeLoanRepayment $repayment,
        PaymentGatewayAttempt $attempt,
        string $destinationType,
        int $destinationId,
    ): void {
        if ($repayment->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($repayment, $attempt, $destinationType, $destinationId) {
            $repayment->refresh();
            $loan = $repayment->employeeLoan()->lockForUpdate()->firstOrFail();

            if ($repayment->status === 'completed') {
                return;
            }

            $allocation = [
                'principal_amount' => (float) $repayment->principal_amount,
                'interest_amount' => (float) $repayment->interest_amount,
                'processing_fee_amount' => (float) $repayment->processing_fee_amount,
                'arrears_interest_amount' => (float) $repayment->arrears_interest_amount,
            ];

            $repayment->update([
                'status' => 'completed',
                'processed_at' => now(),
                'payment_gateway_attempt_id' => $attempt->id,
                'reference' => $repayment->reference ?: ($attempt->provider_reference ?? $attempt->internal_reference),
                'metadata' => array_merge($repayment->metadata ?? [], [
                    'gateway_attempt_id' => $attempt->id,
                    'gateway_confirmed_at' => now()->toIso8601String(),
                    'provider_transaction_id' => $attempt->provider_transaction_id,
                ]),
            ]);

            $this->applyRepaymentToLoan($loan, $repayment->fresh(), $allocation);

            $this->financePostingService->postRepayment(
                $repayment->fresh(),
                $destinationType,
                $destinationId
            );

            app(EmployeeLoanSettlementService::class)->finalizeIfFullyPaid($loan->fresh());
        });
    }

    public function markGatewayRepaymentFailed(EmployeeLoanRepayment $repayment, PaymentGatewayAttempt $attempt): void
    {
        if ($repayment->status === 'completed') {
            return;
        }

        $repayment->update([
            'status' => 'failed',
            'payment_gateway_attempt_id' => $attempt->id,
            'metadata' => array_merge($repayment->metadata ?? [], [
                'gateway_failed_at' => now()->toIso8601String(),
                'gateway_attempt_status' => $attempt->status->value ?? (string) $attempt->status,
                'gateway_failure_message' => $attempt->response_message,
            ]),
        ]);
    }

    /**
     * @param  array<string, float>  $allocation
     */
    protected function applyRepaymentToLoan(EmployeeLoan $loan, EmployeeLoanRepayment $repayment, array $allocation): void
    {
        $scheduleApplied = EmployeeLoanLedgerService::scheduleAppliedAmountFromAllocation($allocation);

        if ($scheduleApplied > 0) {
            $this->scheduleService->applyPaymentToSchedule($loan, $scheduleApplied);
        }

        $this->ledgerService->syncLoanLedger($loan->fresh());
    }

    protected function assertLoanAcceptsRepayment(EmployeeLoan $loan): void
    {
        if (! in_array($loan->status, [EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['loan' => 'Repayments can only be posted to active employee loans.']);
        }
    }

    protected function assertAmountWithinOutstanding(EmployeeLoan $loan, float $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Repayment amount must be greater than zero.']);
        }

        $outstanding = $this->ledgerService->calculateOutstandingBalance($loan);
        if ($amount > $outstanding + 0.01) {
            throw ValidationException::withMessages([
                'amount' => "Repayment exceeds outstanding balance ({$outstanding}).",
            ]);
        }
    }
}
