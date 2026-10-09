<?php

namespace App\Migration\ParallelRun;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrationCustomerInboxRepository
{
    public const STATUS_PENDING = 'pending_review';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>  $context
     */
    public function upsertDetected(int $legacyUserId, array $legacyCustomer, array $context = [], ?string $blockReason = null): void
    {
        $existing = DB::table('migration_customer_inbox')->where('legacy_user_id', $legacyUserId)->first();
        if ($existing && in_array($existing->status, [self::STATUS_IMPORTED, self::STATUS_DISMISSED], true)) {
            return;
        }

        $status = $blockReason ? self::STATUS_BLOCKED : self::STATUS_PENDING;

        DB::table('migration_customer_inbox')->updateOrInsert(
            ['legacy_user_id' => $legacyUserId],
            [
                'status' => $status,
                'block_reason' => $blockReason,
                'raw_snapshot' => json_encode($legacyCustomer),
                'context' => json_encode($context),
                'detected_at' => $existing?->detected_at ?? now(),
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );
    }

    public function countPending(): int
    {
        return (int) DB::table('migration_customer_inbox')
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePending(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_customer_inbox')->orderByDesc('detected_at');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_BLOCKED]);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('legacy_user_id', 'like', "%{$search}%")
                    ->orWhere('raw_snapshot', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByLegacyUserId(int $legacyUserId): ?object
    {
        return DB::table('migration_customer_inbox')->where('legacy_user_id', $legacyUserId)->first();
    }

    public function markImported(int $legacyUserId, int $adminId, int $mappedCustomerId, ?string $notes = null): void
    {
        DB::table('migration_customer_inbox')
            ->where('legacy_user_id', $legacyUserId)
            ->update([
                'status' => self::STATUS_IMPORTED,
                'block_reason' => null,
                'imported_at' => now(),
                'imported_by_admin_id' => $adminId,
                'mapped_customer_id' => $mappedCustomerId,
                'import_notes' => $notes,
                'updated_at' => now(),
            ]);
    }

    public function markBlocked(int $legacyUserId, string $reason): void
    {
        DB::table('migration_customer_inbox')
            ->where('legacy_user_id', $legacyUserId)
            ->update([
                'status' => self::STATUS_BLOCKED,
                'block_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function markDismissed(int $legacyUserId, int $adminId, ?string $notes = null): void
    {
        DB::table('migration_customer_inbox')
            ->where('legacy_user_id', $legacyUserId)
            ->update([
                'status' => self::STATUS_DISMISSED,
                'imported_by_admin_id' => $adminId,
                'import_notes' => $notes,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    public function pendingLegacyUserIds(int $limit = 200): Collection
    {
        return DB::table('migration_customer_inbox')
            ->where('status', self::STATUS_PENDING)
            ->orderBy('legacy_user_id')
            ->limit($limit)
            ->get();
    }
}
