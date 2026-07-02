<?php

namespace App\Services\Repayments;

use App\Models\Bank;
use App\Models\CashRegister;
use App\Models\Repayment;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class RepaymentFinancePostingService
{
    /**
     * Credit the financial account that received a repayment.
     *
     * Idempotent: skips if finance was already posted for this repayment.
     */
    public function creditReceivedAccount(
        Repayment $repayment,
        string $receivedViaType,
        int $receivedViaId,
        ?float $amount = null
    ): void {
        $repayment->refresh();

        if ($this->isAlreadyPosted($repayment)) {
            return;
        }

        $amount = $amount ?? (float) $repayment->total_amount;

        if ($amount <= 0) {
            return;
        }

        match ($receivedViaType) {
            'bank' => Bank::find($receivedViaId)?->updateBalance($amount, 'credit'),
            'wallet' => Wallet::find($receivedViaId)?->updateBalance($amount, 'credit'),
            'cash' => CashRegister::find($receivedViaId)?->updateBalance($amount, 'credit'),
            default => Log::warning('Unknown received_via_type for finance posting', [
                'repayment_id' => $repayment->id,
                'received_via_type' => $receivedViaType,
            ]),
        };

        $metadata = $repayment->metadata ?? [];
        $repayment->update([
            'received_via_type' => $receivedViaType,
            'received_via_id' => $receivedViaId,
            'metadata' => array_merge($metadata, [
                'finance_posted_at' => now()->toIso8601String(),
                'finance_posted_amount' => round($amount, 2),
            ]),
        ]);
    }

    public function isAlreadyPosted(Repayment $repayment): bool
    {
        $metadata = $repayment->metadata ?? [];

        return isset($metadata['finance_posted_at']);
    }
}
