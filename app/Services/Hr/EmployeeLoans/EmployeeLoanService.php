<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeLoanService
{
    public function __construct(
        private readonly EmployeeLoanPricingService $pricingService,
        private readonly EmployeeLoanScheduleService $scheduleService,
    ) {}

    public static function generateLoanNumber(): string
    {
        do {
            $number = 'EL-'.Str::upper(Str::random(10));
        } while (EmployeeLoan::query()->where('loan_number', $number)->exists());

        return $number;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createDraft(array $input, Admin $creator): EmployeeLoan
    {
        $loanRate = LoanRate::query()->findOrFail($input['loan_rate_id']);
        $this->pricingService->assertEmployeeRate($loanRate);

        $quoted = $this->pricingService->quote([
            'loan_rate_id' => $loanRate->id,
            'principal' => $input['principal_amount'],
            'tenure_months' => (int) $input['tenure_months'],
            'start_date' => $input['application_date'] ?? now()->toDateString(),
        ]);

        return EmployeeLoan::create([
            'loan_number' => self::generateLoanNumber(),
            'employee_id' => $input['employee_id'],
            'loan_rate_id' => $loanRate->id,
            'status' => EmployeeLoan::STATUS_DRAFT,
            'disbursement_status' => 'pending',
            'repayment_frequency' => $input['repayment_frequency'] ?? 'monthly',
            'first_payment_date' => $input['first_payment_date'] ?? null,
            'application_date' => $input['application_date'] ?? now()->toDateString(),
            'purpose' => $input['purpose'] ?? null,
            'notes' => $input['notes'] ?? null,
            'disbursement_destination_snapshot' => $input['disbursement_destination_snapshot'] ?? null,
            'created_by' => $creator->id,
            ...$this->mapSnapshotToLoanColumns($quoted['snapshot'], $quoted['quote']),
            'metadata' => array_merge($quoted['snapshot']['metadata'] ?? [], [
                'draft_pricing' => $quoted['quote'],
                'loan_purpose_id' => $input['loan_purpose_id'] ?? null,
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(EmployeeLoan $loan, array $input): EmployeeLoan
    {
        if ($loan->status !== EmployeeLoan::STATUS_DRAFT) {
            throw ValidationException::withMessages(['loan' => 'Only draft employee loans can be edited.']);
        }

        $loanRate = LoanRate::query()->findOrFail($input['loan_rate_id']);
        $this->pricingService->assertEmployeeRate($loanRate);

        $quoted = $this->pricingService->quote([
            'loan_rate_id' => $loanRate->id,
            'principal' => $input['principal_amount'],
            'tenure_months' => (int) $input['tenure_months'],
            'start_date' => $input['application_date'] ?? ($loan->application_date ?? now())->toDateString(),
        ]);

        $loan->update([
            'employee_id' => $input['employee_id'],
            'loan_rate_id' => $loanRate->id,
            'repayment_frequency' => $input['repayment_frequency'] ?? 'monthly',
            'first_payment_date' => $input['first_payment_date'] ?? null,
            'application_date' => $input['application_date'] ?? $loan->application_date,
            'purpose' => $input['purpose'] ?? null,
            'notes' => $input['notes'] ?? null,
            'disbursement_destination_snapshot' => $input['disbursement_destination_snapshot'] ?? null,
            ...$this->mapSnapshotToLoanColumns($quoted['snapshot'], $quoted['quote']),
            'metadata' => array_merge($loan->metadata ?? [], [
                'draft_pricing' => $quoted['quote'],
                'loan_purpose_id' => $input['loan_purpose_id'] ?? null,
            ]),
        ]);

        return $loan->fresh();
    }

    public function submitForApproval(EmployeeLoan $loan): EmployeeLoan
    {
        if ($loan->status !== EmployeeLoan::STATUS_DRAFT) {
            throw ValidationException::withMessages(['loan' => 'Only draft loans can be submitted.']);
        }

        $loan->update([
            'status' => EmployeeLoan::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);

        return $loan->fresh();
    }

    public function approve(EmployeeLoan $loan, Admin $approver): EmployeeLoan
    {
        if ($loan->status !== EmployeeLoan::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['loan' => 'Only pending loans can be approved.']);
        }

        if ((int) $loan->created_by === (int) $approver->id && ! $approver->hasRole('super-admin')) {
            throw ValidationException::withMessages(['loan' => 'You cannot approve a loan you created.']);
        }

        $loanRate = LoanRate::query()->findOrFail($loan->loan_rate_id);
        $this->pricingService->assertEmployeeRate($loanRate);

        $quoted = $this->pricingService->quote([
            'loan_rate_id' => $loanRate->id,
            'principal' => $loan->principal_amount,
            'tenure_months' => (int) $loan->tenure_months,
            'start_date' => ($loan->application_date ?? now())->toDateString(),
        ]);

        return DB::transaction(function () use ($loan, $approver, $quoted) {
            $loan->update([
                'status' => EmployeeLoan::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_date' => now()->toDateString(),
                ...$this->mapSnapshotToLoanColumns($quoted['snapshot'], $quoted['quote']),
                'metadata' => array_merge($loan->metadata ?? [], [
                    'approved_pricing_quote' => $quoted['quote'],
                    'pricing_frozen_at' => now()->toIso8601String(),
                ]),
                'loan_start_date' => $loan->loan_start_date ?? now()->toDateString(),
            ]);

            $loan = $loan->fresh();
            $this->scheduleService->generateForLoan($loan);

            return $loan;
        });
    }

    public function reject(EmployeeLoan $loan, Admin $approver, string $reason): EmployeeLoan
    {
        if ($loan->status !== EmployeeLoan::STATUS_PENDING_APPROVAL) {
            throw ValidationException::withMessages(['loan' => 'Only pending loans can be rejected.']);
        }

        $loan->update([
            'status' => EmployeeLoan::STATUS_REJECTED,
            'rejected_by' => $approver->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $loan->fresh();
    }

    public function cancel(EmployeeLoan $loan, Admin $admin, ?string $reason = null): EmployeeLoan
    {
        if (! in_array($loan->status, [EmployeeLoan::STATUS_DRAFT, EmployeeLoan::STATUS_PENDING_APPROVAL, EmployeeLoan::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['loan' => 'This loan cannot be cancelled.']);
        }

        if ($loan->disbursement_status === 'completed') {
            throw ValidationException::withMessages(['loan' => 'Disbursed loans cannot be cancelled.']);
        }

        $loan->update([
            'status' => EmployeeLoan::STATUS_CANCELLED,
            'cancelled_by' => $admin->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $loan->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapSnapshotToLoanColumns(array $snapshot, array $quote): array
    {
        unset($snapshot['metadata']);

        return array_merge($snapshot, [
            'amount_paid' => 0,
            'metadata' => [
                'pricing_quote' => $quote,
            ],
        ]);
    }

    public function termsAreLocked(EmployeeLoan $loan): bool
    {
        return in_array($loan->status, [
            EmployeeLoan::STATUS_APPROVED,
            EmployeeLoan::STATUS_ACTIVE,
            EmployeeLoan::STATUS_SETTLED,
        ], true) || $loan->disbursement_status === 'completed';
    }
}
