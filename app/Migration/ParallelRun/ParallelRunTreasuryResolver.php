<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Models\Bank;
use App\Models\Wallet;

class ParallelRunTreasuryResolver
{
    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * Resolve legacy expense/repayment payment source (bank or wallet debit/credit).
     *
     * @param  array<string, mixed>|object  $legacyRow
     * @return array{0: ?string, 1: ?int}
     */
    public function resolvePaymentSource(array|object $legacyRow): array
    {
        $row = (array) $legacyRow;

        if (! empty($row['bank_id'])) {
            $bankId = $this->maps->targetId(MigrationEntityMapRepository::TYPE_BANK, (string) $row['bank_id']);
            if ($bankId && Bank::query()->whereKey($bankId)->exists()) {
                return ['bank', $bankId];
            }
        }

        if (! empty($row['wallet_id'])) {
            $wallet = $this->resolveLegacyTreasuryWallet((int) $row['wallet_id']);
            if ($wallet) {
                return ['wallet', $wallet->id];
            }
        }

        return [null, null];
    }

    public function resolveLegacyTreasuryWallet(int $legacyWalletId): ?Wallet
    {
        $mappedId = $this->maps->targetId(MigrationEntityMapRepository::TYPE_TREASURY_WALLET, (string) $legacyWalletId);
        if ($mappedId) {
            return Wallet::query()->whereKey($mappedId)->first();
        }

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $legacyWallet = $legacy->table('payment_wallets')->where('id', $legacyWalletId)->first();
            if (! $legacyWallet) {
                return null;
            }

            $matcher = app(\App\Migration\Phases\Support\ReferenceMatcher::class);
            $wallet = $matcher->matchTreasuryWalletRecord((array) $legacyWallet);

            if ($wallet) {
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_TREASURY_WALLET,
                    (string) $legacyWalletId,
                    Wallet::class,
                    $wallet->id,
                    'matched_by_name',
                    'MEDIUM',
                );
            }

            return $wallet;
        } catch (\Throwable) {
            return null;
        }
    }

    public function resolveDefaultDisbursementWallet(): ?Wallet
    {
        $legacyCode = (string) config('legacy-parallel-run.default_disbursement_wallet_code', 'KAZANG');

        $wallet = Wallet::query()
            ->where(function ($query) use ($legacyCode) {
                $query->where('code', $legacyCode)
                    ->orWhere('name', 'like', '%'.$legacyCode.'%');
            })
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if ($wallet) {
            return $wallet;
        }

        $configuredId = config('legacy-parallel-run.default_disbursement_wallet_id');

        return $configuredId
            ? Wallet::query()->whereKey((int) $configuredId)->first()
            : null;
    }

    public function resolveDefaultCollectionWallet(): ?Wallet
    {
        return $this->resolveDefaultDisbursementWallet();
    }
}
