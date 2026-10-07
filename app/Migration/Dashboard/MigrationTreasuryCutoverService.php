<?php

namespace App\Migration\Dashboard;

use App\Models\Bank;
use App\Models\Wallet;
use App\Migration\ParallelRun\MigrationSyncState;
use Illuminate\Support\Facades\DB;

class MigrationTreasuryCutoverService
{
    public function __construct(
        private readonly MigrationSyncState $syncState,
    ) {}

    /**
     * @return array{
     *     finance_on_import_enabled: bool,
     *     last_cutover_at: ?string,
     *     mismatched_count: int,
     *     accounts: list<array<string, mixed>>
     * }
     */
    public function summary(): array
    {
        $accounts = $this->accountRows();
        $mismatched = collect($accounts)->filter(
            fn (array $row) => abs((float) $row['current_balance'] - (float) $row['opening_balance']) >= 0.01
        );

        return [
            'finance_on_import_enabled' => (bool) config('legacy-parallel-run.finance_on_import_enabled', false),
            'last_cutover_at' => $this->syncState->get(MigrationSyncState::KEY_TREASURY_CUTOVER_AT),
            'mismatched_count' => $mismatched->count(),
            'accounts' => $accounts,
        ];
    }

    /**
     * Set current_balance = opening_balance on all treasury banks and wallets.
     *
     * @return array{updated: int, skipped: int, accounts: list<array<string, mixed>>}
     */
    public function syncCurrentToOpening(?int $adminId = null): array
    {
        $updated = 0;
        $skipped = 0;
        $changes = [];

        DB::transaction(function () use (&$updated, &$skipped, &$changes, $adminId) {
            foreach (Wallet::query()->orderBy('id')->get() as $wallet) {
                $result = $this->applyCutoverToAccount(
                    'wallet',
                    $wallet->id,
                    $wallet->name,
                    (float) $wallet->opening_balance,
                    (float) $wallet->current_balance,
                    $adminId,
                );
                $result['action'] === 'updated' ? $updated++ : $skipped++;
                $changes[] = $result;
                if ($result['action'] === 'updated') {
                    $wallet->update(['current_balance' => $result['new_current_balance']]);
                }
            }

            foreach (Bank::query()->orderBy('id')->get() as $bank) {
                $result = $this->applyCutoverToAccount(
                    'bank',
                    $bank->id,
                    $bank->name,
                    (float) $bank->opening_balance,
                    (float) $bank->current_balance,
                    $adminId,
                );
                $result['action'] === 'updated' ? $updated++ : $skipped++;
                $changes[] = $result;
                if ($result['action'] === 'updated') {
                    $bank->update(['current_balance' => $result['new_current_balance']]);
                }
            }

            $this->syncState->put(
                MigrationSyncState::KEY_TREASURY_CUTOVER_AT,
                now()->toIso8601String().($adminId ? "|admin:{$adminId}" : ''),
            );
        });

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'accounts' => $changes,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountRows(): array
    {
        $rows = [];

        foreach (Wallet::query()->orderBy('name')->get() as $wallet) {
            $rows[] = [
                'type' => 'wallet',
                'id' => $wallet->id,
                'name' => $wallet->name,
                'currency' => $wallet->currency,
                'opening_balance' => (float) $wallet->opening_balance,
                'current_balance' => (float) $wallet->current_balance,
                'is_active' => (bool) $wallet->is_active,
            ];
        }

        foreach (Bank::query()->orderBy('name')->get() as $bank) {
            $rows[] = [
                'type' => 'bank',
                'id' => $bank->id,
                'name' => $bank->name,
                'currency' => $bank->currency,
                'opening_balance' => (float) $bank->opening_balance,
                'current_balance' => (float) $bank->current_balance,
                'is_active' => (bool) $bank->is_active,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function applyCutoverToAccount(
        string $type,
        int $id,
        string $name,
        float $openingBalance,
        float $currentBalance,
        ?int $adminId,
    ): array {
        $matches = abs($currentBalance - $openingBalance) < 0.01;

        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'action' => $matches ? 'skipped' : 'updated',
            'previous_current_balance' => round($currentBalance, 2),
            'new_current_balance' => round($openingBalance, 2),
            'opening_balance' => round($openingBalance, 2),
            'admin_id' => $adminId,
        ];
    }
}
