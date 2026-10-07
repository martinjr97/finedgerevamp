<?php

namespace App\Migration\ParallelRun;

use App\Models\Repayment;
use App\Services\Repayments\RepaymentFinancePostingService;

class ParallelRunRepaymentFinanceService
{
    public function __construct(
        private readonly ParallelRunTreasuryResolver $treasury,
        private readonly RepaymentFinancePostingService $repaymentPosting,
    ) {}

    /**
     * Credit treasury from legacy repayment bank/wallet fields.
     *
     * @param  array<string, mixed>  $legacyRepayment
     * @return array<string, mixed>
     */
    public function postCollectionFromLegacy(Repayment $repayment, array $legacyRepayment): array
    {
        if (! config('legacy-parallel-run.finance_on_import_enabled', false)) {
            return ['status' => 'disabled'];
        }

        $repayment->refresh();

        if ($this->repaymentPosting->isAlreadyPosted($repayment)) {
            return ['status' => 'already_posted'];
        }

        [$receivedViaType, $receivedViaId] = $this->treasury->resolvePaymentSource($legacyRepayment);

        if (! $receivedViaType || ! $receivedViaId) {
            $wallet = $this->treasury->resolveDefaultCollectionWallet();
            if ($wallet) {
                $receivedViaType = 'wallet';
                $receivedViaId = $wallet->id;
            }
        }

        if (! $receivedViaType || ! $receivedViaId) {
            return ['status' => 'skipped', 'message' => 'Treasury destination not mapped'];
        }

        $this->repaymentPosting->creditReceivedAccount(
            $repayment,
            $receivedViaType,
            $receivedViaId,
        );

        $metadata = $repayment->metadata ?? [];
        $repayment->update([
            'metadata' => array_merge($metadata, [
                'parallel_run_finance' => true,
                'legacy_repayment_bank_id' => $legacyRepayment['bank_id'] ?? null,
                'legacy_repayment_wallet_id' => $legacyRepayment['wallet_id'] ?? null,
            ]),
        ]);

        return [
            'status' => 'posted',
            'received_via_type' => $receivedViaType,
            'received_via_id' => $receivedViaId,
        ];
    }
}
