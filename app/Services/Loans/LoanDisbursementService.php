<?php

namespace App\Services\Loans;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Loan;
use App\Models\PaymentGatewayAttempt;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\Services\CustomerNotificationService;
use App\Services\LoanPaymentDetailsService;
use App\Services\Loans\DTOs\ManualDisbursementDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoanDisbursementService
{
    public function __construct(
        private readonly LoanDisbursementFinancePostingService $financePostingService,
        private readonly LoanPaymentDetailsService $loanPaymentDetailsService,
        private readonly CustomerNotificationService $customerNotificationService,
    ) {}

    public function assertCanDisburse(Loan $loan): void
    {
        if ($loan->status !== 'approved' || ! in_array($loan->disbursement_status, ['pending', 'failed'], true)) {
            throw ValidationException::withMessages([
                'loan' => 'Only approved loans with pending or failed disbursement can be disbursed.',
            ]);
        }
    }

    public function assertNoActiveDisbursementAttempt(Loan $loan): void
    {
        if ($loan->disbursement_status === 'processing') {
            throw ValidationException::withMessages([
                'loan' => 'A disbursement is already in progress for this loan.',
            ]);
        }

        $activeAttempt = PaymentGatewayAttempt::query()
            ->where('attemptable_type', Loan::class)
            ->where('attemptable_id', $loan->id)
            ->where('direction', GatewayDirection::Disbursement)
            ->whereNotIn('status', [
                GatewayAttemptStatus::Failed,
                GatewayAttemptStatus::Rejected,
                GatewayAttemptStatus::Expired,
                GatewayAttemptStatus::Cancelled,
            ])
            ->exists();

        if ($activeAttempt) {
            throw ValidationException::withMessages([
                'loan' => 'A gateway disbursement attempt is already active for this loan.',
            ]);
        }
    }

    public function completeManualDisbursement(
        Loan $loan,
        ManualDisbursementDTO $dto,
        Admin $admin,
        ?array $paymentDetailsChange = null
    ): void {
        $this->assertCanDisburse($loan);

        $amount = (float) $loan->principal_amount;

        DB::transaction(function () use ($loan, $dto, $admin, $amount, $paymentDetailsChange) {
            if ($dto->sourceType === 'bank') {
                $source = Bank::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($dto->sourceId);
            } else {
                $source = Wallet::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($dto->sourceId);
            }

            if ((float) $source->current_balance < $amount) {
                throw ValidationException::withMessages([
                    'source_id' => 'Insufficient balance on the selected account. Available: '.number_format((float) $source->current_balance, 2),
                ]);
            }

            $this->financePostingService->debitSourceAccount(
                $loan,
                $dto->sourceType,
                $dto->sourceId,
                $amount
            );

            $loan->refresh();
            $loan->applyDisbursementCompleted($dto->disbursementDate);
            $loan->disbursement_reference = $dto->referenceNumber;
            $loan->disbursement_notes = $dto->description;
            $loan->metadata = array_merge($loan->metadata ?? [], [
                'disbursement_reference' => $dto->referenceNumber,
                'disbursed_manually_by' => $admin->id,
            ]);
            $loan->save();

            if ($paymentDetailsChange) {
                $this->loanPaymentDetailsService->recordAudit($loan, $paymentDetailsChange, $admin);
            }
        });

        $freshLoan = $loan->fresh(['customer', 'loanProduct', 'channel']);

        if ($paymentDetailsChange) {
            try {
                $this->loanPaymentDetailsService->sendChangeNotification($freshLoan, $paymentDetailsChange);
            } catch (\Throwable $notificationError) {
                Log::error('Failed to send loan payment details change notifications', [
                    'loan_id' => $loan->id,
                    'error' => $notificationError->getMessage(),
                ]);
            }
        }

        try {
            $this->customerNotificationService->sendLoanDisbursed($freshLoan);
        } catch (\Throwable $notificationError) {
            Log::error('Failed to send loan disbursement notifications', [
                'loan_id' => $loan->id,
                'error' => $notificationError->getMessage(),
            ]);
        }
    }

    public function markGatewayProcessing(Loan $loan, PaymentGatewayAttempt $attempt): void
    {
        $loan->update([
            'disbursement_status' => 'processing',
            'payment_gateway_attempt_id' => $attempt->id,
            'metadata' => array_merge($loan->metadata ?? [], [
                'gateway_code' => $attempt->paymentGateway?->code,
                'gateway_attempt_id' => $attempt->id,
                'gateway_disbursement_initiated_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function completeGatewayDisbursement(
        Loan $loan,
        PaymentGatewayAttempt $attempt,
        ?Carbon $disbursedAt = null
    ): void {
        $gateway = $attempt->paymentGateway;
        if (! $gateway || ! $gateway->hasLinkedFinancialAccount()) {
            $loan->update([
                'metadata' => array_merge($loan->metadata ?? [], [
                    'requires_finance_reconciliation' => true,
                    'gateway_confirmed_at' => now()->toIso8601String(),
                    'gateway_attempt_id' => $attempt->id,
                ]),
            ]);

            return;
        }

        $accountType = $gateway->financial_account_type->value;
        $accountId = (int) $gateway->financial_account_id;
        $amount = (float) $loan->principal_amount;

        DB::transaction(function () use ($loan, $attempt, $accountType, $accountId, $amount, $disbursedAt) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($lockedLoan->disbursement_status === 'completed') {
                return;
            }

            $account = match ($accountType) {
                'bank' => Bank::query()->lockForUpdate()->find($accountId),
                'wallet' => Wallet::query()->lockForUpdate()->find($accountId),
                default => null,
            };

            if (! $account) {
                $lockedLoan->update([
                    'metadata' => array_merge($lockedLoan->metadata ?? [], [
                        'requires_finance_reconciliation' => true,
                        'gateway_confirmed_at' => now()->toIso8601String(),
                    ]),
                ]);

                return;
            }

            $balanceBefore = (float) $account->current_balance;

            $this->financePostingService->debitSourceAccount($lockedLoan, $accountType, $accountId, $amount);

            $lockedLoan->refresh();
            $lockedLoan->applyDisbursementCompleted($disbursedAt ?? now());
            $lockedLoan->disbursement_reference = $attempt->provider_reference ?? $attempt->internal_reference;
            $lockedLoan->metadata = array_merge($lockedLoan->metadata ?? [], [
                'disbursement_reference' => $attempt->provider_reference ?? $attempt->internal_reference,
                'disbursed_via_gateway' => $attempt->paymentGateway?->code,
                'gateway_attempt_id' => $attempt->id,
                'finance_balance_before_disbursement' => round($balanceBefore, 2),
                'finance_posted_below_zero_balance' => $balanceBefore < $amount,
            ]);
            $lockedLoan->save();
        });

        $freshLoan = $loan->fresh(['customer', 'loanProduct', 'channel']);

        if ($freshLoan->disbursement_status === 'completed') {
            try {
                $this->customerNotificationService->sendLoanDisbursed($freshLoan);
            } catch (\Throwable $notificationError) {
                Log::error('Failed to send gateway disbursement notifications', [
                    'loan_id' => $loan->id,
                    'error' => $notificationError->getMessage(),
                ]);
            }
        }
    }

    public function markDisbursementFailed(Loan $loan, PaymentGatewayAttempt $attempt): void
    {
        if ($loan->disbursement_status === 'completed') {
            return;
        }

        $loan->update([
            'disbursement_status' => 'failed',
            'metadata' => array_merge($loan->metadata ?? [], [
                'gateway_failed_at' => now()->toIso8601String(),
                'gateway_attempt_status' => $attempt->status->value,
                'gateway_failure_message' => $attempt->response_message,
            ]),
        ]);
    }
}
