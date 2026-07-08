<?php

namespace App\Services\Loans;

use App\Models\Admin;
use App\Models\Loan;
use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanCancellationService
{
    public function canCancel(Loan $loan): bool
    {
        try {
            $this->assertCanCancel($loan);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function assertCanCancel(Loan $loan): void
    {
        if ($loan->status === 'pending_approval') {
            return;
        }

        if ($loan->status === 'cancelled') {
            throw ValidationException::withMessages([
                'loan' => 'This loan is already cancelled.',
            ]);
        }

        if ($loan->status !== 'approved') {
            throw ValidationException::withMessages([
                'loan' => 'Only pending or approved loans that have not been disbursed can be cancelled.',
            ]);
        }

        if ($loan->disbursement_status === 'completed') {
            throw ValidationException::withMessages([
                'loan' => 'This loan has already been disbursed and cannot be cancelled.',
            ]);
        }

        if ($loan->disbursement_status === 'processing') {
            throw ValidationException::withMessages([
                'loan' => 'A disbursement is in progress. Wait for it to complete or fail before cancelling.',
            ]);
        }

        if (! in_array($loan->disbursement_status, ['pending', 'failed'], true)) {
            throw ValidationException::withMessages([
                'loan' => 'Only loans with pending or failed disbursement can be cancelled.',
            ]);
        }

        if ($this->hasActiveDisbursementAttempt($loan)) {
            throw ValidationException::withMessages([
                'loan' => 'A gateway disbursement attempt is still active for this loan. Cancel is blocked until that attempt finishes.',
            ]);
        }
    }

    public function cancel(Loan $loan, Admin $admin, ?string $notes = null): Loan
    {
        $this->assertCanCancel($loan);

        return DB::transaction(function () use ($loan, $admin, $notes) {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $this->assertCanCancel($locked);

            $metadata = $locked->metadata ?? [];
            $metadata['cancellation'] = [
                'cancelled_by' => $admin->id,
                'cancelled_at' => now()->toIso8601String(),
                'previous_status' => $locked->status,
                'previous_disbursement_status' => $locked->disbursement_status,
                'notes' => $notes,
            ];

            $locked->update([
                'status' => 'cancelled',
                'approval_notes' => $notes ?: $locked->approval_notes,
                'metadata' => $metadata,
            ]);

            return $locked->fresh();
        });
    }

    private function hasActiveDisbursementAttempt(Loan $loan): bool
    {
        return PaymentGatewayAttempt::query()
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
    }
}
