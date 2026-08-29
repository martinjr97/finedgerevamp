<?php

namespace App\Migration;

class BankWalletAnalyzer
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @return array<string, mixed>
     */
    public function analyzeForUsers(array $userIds): array
    {
        $db = LegacyConnection::connection();

        $customers = $db->table('customers')->whereIn('user_id', $userIds)->get()->map(fn ($r) => (array) $r);
        $users = $db->table('users')->whereIn('id', $userIds)->get()->keyBy('id')->map(fn ($r) => (array) $r);

        $withBank = 0;
        $withoutBank = 0;
        $withWallet = 0;
        $withoutWallet = 0;
        $invalidBanks = 0;
        $invalidWallets = 0;
        $duplicateWalletNumbers = [];
        $bankRecords = [];
        $walletRecords = [];

        $legacyBanks = $db->table('banks')->get()->map(fn ($r) => (array) $r);
        $legacyWallets = $db->table('payment_wallets')->get()->map(fn ($r) => (array) $r);

        $walletNumberCounts = [];

        foreach ($customers as $customer) {
            $userId = (int) $customer['user_id'];
            $user = $users->get($userId);
            $hasBank = $this->hasBankAccount($customer);
            $hasWallet = $this->hasWalletNumber($user);

            $hasBank ? $withBank++ : $withoutBank++;
            $hasWallet ? $withWallet++ : $withoutWallet++;

            if ($hasBank) {
                $bankRecords[] = [
                    'legacy_user_id' => $userId,
                    'legacy_customer_id' => $customer['id'],
                    'bank_name' => $customer['account_bank_name'] ?? null,
                    'branch_name' => $customer['account_branch_name'] ?? null,
                    'sort_code' => $customer['account_branch_sort_code'] ?? null,
                    'account_number' => $customer['bank_account_number'] ?? null,
                    'account_name' => trim(($user['fname'] ?? '').' '.($user['lname'] ?? '')),
                    'confidence' => $this->bankConfidence($customer),
                ];
                if ($this->bankConfidence($customer) === 'MANUAL_REVIEW') {
                    $invalidBanks++;
                }
            }

            if ($hasWallet) {
                $normalized = $this->phoneNormalizer->normalize($user['phone_number'] ?? null);
                $provider = $this->phoneNormalizer->inferProvider($normalized);
                $walletRecords[] = [
                    'legacy_user_id' => $userId,
                    'legacy_customer_id' => $customer['id'],
                    'wallet_number' => $user['phone_number'] ?? null,
                    'wallet_number_normalized' => $normalized,
                    'provider_code' => $provider,
                    'inferred_from' => 'users.phone_number',
                    'confidence' => $provider ? 'MEDIUM' : 'LOW',
                ];
                if ($normalized) {
                    $walletNumberCounts[$normalized] = ($walletNumberCounts[$normalized] ?? 0) + 1;
                }
                if (! $normalized) {
                    $invalidWallets++;
                }
            }
        }

        foreach ($walletNumberCounts as $number => $count) {
            if ($count > 1) {
                $duplicateWalletNumbers[] = ['wallet_number_normalized' => $number, 'customer_count' => $count];
            }
        }

        return [
            'legacy_treasury_banks' => $legacyBanks->count(),
            'legacy_treasury_wallets' => $legacyWallets->count(),
            'legacy_banks' => $legacyBanks->values()->all(),
            'legacy_payment_wallets' => $legacyWallets->values()->all(),
            'active_customers_with_bank' => $withBank,
            'active_customers_without_bank' => $withoutBank,
            'active_customers_with_wallet' => $withWallet,
            'active_customers_without_wallet' => $withoutWallet,
            'customers_with_multiple_bank_accounts' => 0,
            'bank_account_multiple_target_limitation' => true,
            'invalid_bank_accounts' => $invalidBanks,
            'invalid_wallets' => $invalidWallets,
            'duplicate_wallet_numbers' => $duplicateWalletNumbers,
            'bank_records' => $bankRecords,
            'wallet_records' => $walletRecords,
            'bank_mapping' => $this->buildBankMapping($legacyBanks->all()),
            'wallet_provider_mapping' => $this->buildWalletProviderMapping($legacyWallets->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function hasBankAccount(array $customer): bool
    {
        return ! empty($customer['bank_account_number']) || ! empty($customer['account_bank_name']);
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    private function hasWalletNumber(?array $user): bool
    {
        return ! empty($user['phone_number']);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function bankConfidence(array $customer): string
    {
        if (empty($customer['bank_account_number']) || empty($customer['account_bank_name'])) {
            return 'MANUAL_REVIEW';
        }

        return 'HIGH';
    }

    /**
     * @param  list<array<string, mixed>>  $legacyBanks
     * @return list<array<string, mixed>>
     */
    private function buildBankMapping(array $legacyBanks): array
    {
        $target = [
            ['legacy' => 'ZICB', 'target_code' => null, 'target_name' => 'Map manually'],
            ['legacy' => 'FNB', 'target_code' => 'FNB', 'target_name' => 'FNB Zambia'],
        ];

        $out = [];
        foreach ($legacyBanks as $bank) {
            $match = collect($target)->first(fn ($t) => strtoupper($bank['code'] ?? '') === strtoupper($t['legacy']));
            $out[] = [
                'legacy_bank_id' => $bank['id'],
                'legacy_name' => $bank['name'],
                'legacy_code' => $bank['code'],
                'target_financial_institution_code' => $match['target_code'] ?? null,
                'confidence' => $match ? 'HIGH' : 'MANUAL_REVIEW',
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $legacyWallets
     * @return list<array<string, mixed>>
     */
    private function buildWalletProviderMapping(array $legacyWallets): array
    {
        $map = [
            'AIRTEL' => 'AIRTEL_MONEY',
            'MTN' => 'MTN_MONEY',
            'ZAMTEL' => 'ZAMTEL_MONEY',
            'KAZANG' => null,
        ];

        $out = [];
        foreach ($legacyWallets as $wallet) {
            $code = strtoupper($wallet['code'] ?? '');
            $target = null;
            foreach ($map as $needle => $targetCode) {
                if (str_contains($code, $needle)) {
                    $target = $targetCode;
                    break;
                }
            }
            $out[] = [
                'legacy_wallet_id' => $wallet['id'],
                'legacy_name' => $wallet['name'],
                'legacy_code' => $wallet['code'],
                'target_wallet_provider_code' => $target,
                'is_treasury' => true,
                'confidence' => $target ? 'HIGH' : 'MANUAL_REVIEW',
            ];
        }

        return $out;
    }
}
