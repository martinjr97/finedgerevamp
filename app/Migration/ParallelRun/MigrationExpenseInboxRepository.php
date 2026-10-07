<?php

namespace App\Migration\ParallelRun;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrationExpenseInboxRepository
{
    public const STATUS_PENDING = 'pending_sync';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>  $legacyExpense
     */
    public function upsertDetected(int $legacyExpenseId, array $legacyExpense): void
    {
        $existing = DB::table('migration_expense_inbox')
            ->where('legacy_expense_id', $legacyExpenseId)
            ->first();

        if ($existing && in_array($existing->status, [self::STATUS_SYNCED, self::STATUS_SKIPPED], true)) {
            return;
        }

        DB::table('migration_expense_inbox')->updateOrInsert(
            ['legacy_expense_id' => $legacyExpenseId],
            [
                'amount' => (float) ($legacyExpense['amount'] ?? 0),
                'status' => self::STATUS_PENDING,
                'raw_snapshot' => json_encode($legacyExpense),
                'detected_at' => $existing?->detected_at ?? now(),
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );
    }

    public function countPending(): int
    {
        return (int) DB::table('migration_expense_inbox')
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    /**
     * @return Collection<int, object>
     */
    public function pendingRows(int $limit = 100): Collection
    {
        return DB::table('migration_expense_inbox')
            ->where('status', self::STATUS_PENDING)
            ->orderBy('legacy_expense_id')
            ->limit($limit)
            ->get();
    }

    public function markSynced(int $legacyExpenseId, ?int $mappedTransactionId): void
    {
        DB::table('migration_expense_inbox')
            ->where('legacy_expense_id', $legacyExpenseId)
            ->update([
                'status' => self::STATUS_SYNCED,
                'mapped_transaction_id' => $mappedTransactionId,
                'synced_at' => now(),
                'sync_error' => null,
                'updated_at' => now(),
            ]);
    }

    public function markFailed(int $legacyExpenseId, string $error): void
    {
        DB::table('migration_expense_inbox')
            ->where('legacy_expense_id', $legacyExpenseId)
            ->update([
                'status' => self::STATUS_FAILED,
                'sync_error' => $error,
                'updated_at' => now(),
            ]);
    }

    public function markSkipped(int $legacyExpenseId, string $reason): void
    {
        DB::table('migration_expense_inbox')
            ->where('legacy_expense_id', $legacyExpenseId)
            ->update([
                'status' => self::STATUS_SKIPPED,
                'sync_error' => $reason,
                'synced_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
