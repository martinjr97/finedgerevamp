<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Admin;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanRepayment;
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
        if (! in_array($loan->status, [EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['loan' => 'Repayments can only be posted to active employee loans.']);
        }

        $amount = round((float) $input['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Repayment amount must be greater than zero.']);
        }

        $outstanding = $this->ledgerService->calculateOutstandingBalance($loan);
        if ($amount > $outstanding + 0.01) {
            throw ValidationException::withMessages([
                'amount' => "Repayment exceeds outstanding balance ({$outstanding}).",
            ]);
        }

        $allocation = $this->ledgerService->calculateRepaymentAllocation($loan, $amount);
        $scheduleApplied = EmployeeLoanLedgerService::scheduleAppliedAmountFromAllocation($allocation);

        return DB::transaction(function () use ($loan, $input, $processor, $amount, $allocation, $scheduleApplied) {
            $repayment = EmployeeLoanRepayment::create([
                'employee_loan_id' => $loan->id,
                'amount' => $amount,
                'principal_amount' => $allocation['principal_amount'],
                'interest_amount' => $allocation['interest_amount'],
                'processing_fee_amount' => $allocation['processing_fee_amount'],
                'arrears_interest_amount' => $allocation['arrears_interest_amount'],
                'effective_date' => Carbon::parse($input['effective_date'])->toDateString(),
                'processed_at' => now(),
                'payment_method' => $input['payment_method'] ?? null,
                'repayment_source' => $input['repayment_source'] ?? EmployeeLoanRepayment::SOURCE_MANUAL,
                'reference' => $input['reference'] ?? null,
                'processed_by' => $processor->id,
                'status' => 'completed',
                'notes' => $input['notes'] ?? null,
            ]);

            if ($scheduleApplied > 0) {
                $this->scheduleService->applyPaymentToSchedule($loan, $scheduleApplied);
            }

            $this->ledgerService->syncLoanLedger($loan->fresh());

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
}
