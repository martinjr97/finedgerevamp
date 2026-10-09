<?php

namespace App\Services\Hr\EmployeeLoans;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\EmployeeLoan;
use App\Models\PaymentGatewayAttempt;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\Services\Loans\DTOs\ManualDisbursementDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeLoanDisbursementService
{
    public function __construct(
        private readonly EmployeeLoanFinancePostingService $financePostingService,
        private readonly EmployeeLoanNotificationService $notificationService,
    ) {}

    public function assertCanDisburse(EmployeeLoan $loan): void
    {
        if ($loan->status !== EmployeeLoan::STATUS_APPROVED || ! in_array($loan->disbursement_status, ['pending', 'failed'], true)) {
            throw ValidationException::withMessages([
                'loan' => 'Only approved employee loans with pending or failed disbursement can be disbursed.',
            ]);
        }
    }

    public function assertNoActiveDisbursementAttempt(EmployeeLoan $loan): void
    {
        if ($loan->disbursement_status === 'processing') {
            throw ValidationException::withMessages([
                'loan' => 'A disbursement is already in progress for this employee loan.',
            ]);
        }

        $activeAttempt = PaymentGatewayAttempt::query()
            ->where('attemptable_type', EmployeeLoan::class)
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
                'loan' => 'A gateway disbursement attempt is already active for this employee loan.',
            ]);
        }
    }

    public function completeManualDisbursement(
        EmployeeLoan $loan,
        ManualDisbursementDTO $dto,
        Admin $admin,
        bool $externalAlreadyPaid = false,
        ?array $destinationSnapshot = null,
    ): EmployeeLoan {
        $this->assertCanDisburse($loan);

        if ($loan->paymentSchedules()->doesntExist()) {
            throw ValidationException::withMessages(['loan' => 'Approved loan is missing a repayment schedule.']);
        }

        $amount = (float) $loan->principal_amount;

        return DB::transaction(function () use ($loan, $dto, $admin, $externalAlreadyPaid, $destinationSnapshot, $amount) {
            $locked = EmployeeLoan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($locked->disbursement_status === 'completed') {
                throw ValidationException::withMessages(['loan' => 'This employee loan has already been disbursed.']);
            }

            if ($dto->sourceType === 'bank') {
                $source = Bank::query()->where('is_active', true)->lockForUpdate()->findOrFail($dto->sourceId);
            } else {
                $source = Wallet::query()->where('is_active', true)->lockForUpdate()->findOrFail($dto->sourceId);
            }

            if ((float) $source->current_balance < $amount) {
                throw ValidationException::withMessages([
                    'source_id' => 'Insufficient balance on the selected account. Available: '.number_format((float) $source->current_balance, 2),
                ]);
            }

            $this->financePostingService->postDisbursement($locked, $dto->sourceType, $dto->sourceId, $amount);

            $locked->refresh();
            $locked->applyDisbursementCompleted($dto->disbursementDate);
            $locked->disbursement_reference = $dto->referenceNumber;
            $locked->disbursement_notes = $dto->description;
            $locked->disbursed_by = $admin->id;
            if ($destinationSnapshot !== null) {
                $locked->disbursement_destination_snapshot = $destinationSnapshot;
            }
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'disbursement_reference' => $dto->referenceNumber,
                'disbursed_manually_by' => $admin->id,
                'disbursement_recorded_externally' => $externalAlreadyPaid,
            ]);
            $locked->save();

            return $locked->fresh();
        });
    }

    public function markGatewayProcessing(EmployeeLoan $loan, PaymentGatewayAttempt $attempt): void
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
        EmployeeLoan $loan,
        PaymentGatewayAttempt $attempt,
        ?Carbon $disbursedAt = null,
        ?Admin $admin = null,
    ): void {
        $gateway = $attempt->paymentGateway;
        $amount = (float) $loan->principal_amount;

        DB::transaction(function () use ($loan, $attempt, $disbursedAt, $admin, $gateway, $amount) {
            $locked = EmployeeLoan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($locked->disbursement_status === 'completed') {
                return;
            }

            if ($gateway && $gateway->hasLinkedFinancialAccount()) {
                $accountType = $gateway->financial_account_type->value;
                $accountId = (int) $gateway->financial_account_id;
                $this->financePostingService->postDisbursement($locked, $accountType, $accountId, $amount);
            }

            $locked->refresh();
            $locked->applyDisbursementCompleted($disbursedAt ?? now());
            $locked->disbursement_reference = $attempt->provider_reference ?? $attempt->internal_reference;
            $locked->disbursed_by = $admin?->id ?? $locked->disbursed_by;
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'disbursement_reference' => $locked->disbursement_reference,
                'disbursed_via_gateway' => $gateway?->code,
                'gateway_attempt_id' => $attempt->id,
            ]);
            $locked->save();
        });

        if ($loan->fresh()->disbursement_status === 'completed') {
            try {
                $this->notificationService->sendDisbursed($loan->fresh());
            } catch (\Throwable) {
                // logged inside notification service
            }
        }
    }

    public function markDisbursementFailed(EmployeeLoan $loan, PaymentGatewayAttempt $attempt): void
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

    /** @deprecated Use completeManualDisbursement */
    public function disburse(
        EmployeeLoan $loan,
        Admin $admin,
        string $sourceType,
        int $sourceId,
        ?array $destinationSnapshot = null
    ): EmployeeLoan {
        $completed = $this->completeManualDisbursement(
            $loan,
            new ManualDisbursementDTO(
                sourceType: $sourceType,
                sourceId: $sourceId,
                referenceNumber: $loan->loan_number.'-MANUAL',
                disbursementDate: Carbon::now(),
                description: 'Manual employee loan disbursement',
            ),
            $admin,
            false,
            $destinationSnapshot,
        );

        try {
            $this->notificationService->sendDisbursed($completed);
        } catch (\Throwable) {
        }

        return $completed;
    }
}
