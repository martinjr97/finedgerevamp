<?php

namespace App\Migration\ParallelRun;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrationRepaymentInboxRepository
{
    public const STATUS_PENDING = 'pending_sync';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>  $legacyRepayment
     */
    public function upsertDetected(int $legacyRepaymentId, int $legacyUserId, array $legacyRepayment): void
    {
        $existing = DB::table('migration_repayment_inbox')
            ->where('legacy_repayment_id', $legacyRepaymentId)
            ->first();

        if ($existing && in_array($existing->status, [self::STATUS_SYNCED, self::STATUS_SKIPPED], true)) {
            return;
        }

        DB::table('migration_repayment_inbox')->updateOrInsert(
            ['legacy_repayment_id' => $legacyRepaymentId],
            [
                'legacy_user_id' => $legacyUserId,
                'repayment_amount' => (float) ($legacyRepayment['repayment_amount'] ?? 0),
                'status' => self::STATUS_PENDING,
                'raw_snapshot' => json_encode($legacyRepayment),
                'detected_at' => $existing?->detected_at ?? now(),
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );
    }

    public function countPending(): int
    {
        return (int) DB::table('migration_repayment_inbox')
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    /**
     * @return Collection<int, object>
     */
    public function pendingRows(int $limit = 100): Collection
    {
        return DB::table('migration_repayment_inbox')
            ->where('status', self::STATUS_PENDING)
            ->orderBy('legacy_repayment_id')
            ->limit($limit)
            ->get();
    }

    public function markSynced(int $legacyRepaymentId, ?int $mappedRepaymentId, ?string $attributionClass = null): void
    {
        DB::table('migration_repayment_inbox')
            ->where('legacy_repayment_id', $legacyRepaymentId)
            ->update([
                'status' => self::STATUS_SYNCED,
                'attribution_class' => $attributionClass,
                'mapped_repayment_id' => $mappedRepaymentId,
                'synced_at' => now(),
                'sync_error' => null,
                'updated_at' => now(),
            ]);
    }

    public function markFailed(int $legacyRepaymentId, string $error, ?string $attributionClass = null): void
    {
        DB::table('migration_repayment_inbox')
            ->where('legacy_repayment_id', $legacyRepaymentId)
            ->update([
                'status' => self::STATUS_FAILED,
                'attribution_class' => $attributionClass,
                'sync_error' => $error,
                'updated_at' => now(),
            ]);
    }

    public function markSkipped(int $legacyRepaymentId, string $reason, ?string $attributionClass = null): void
    {
        DB::table('migration_repayment_inbox')
            ->where('legacy_repayment_id', $legacyRepaymentId)
            ->update([
                'status' => self::STATUS_SKIPPED,
                'attribution_class' => $attributionClass,
                'sync_error' => $reason,
                'updated_at' => now(),
            ]);
    }
}
