<?php

namespace App\Migration\ParallelRun;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrationLoanInboxRepository
{
    public const STATUS_PENDING = 'pending_review';

    public const STATUS_IMPORTED = 'imported';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * @param  array<string, mixed>  $legacyLoan
     */
    public function upsertDetected(int $legacyLoanId, int $legacyUserId, array $legacyLoan, ?string $blockReason = null): void
    {
        $existing = DB::table('migration_loan_inbox')->where('legacy_loan_id', $legacyLoanId)->first();
        if ($existing && in_array($existing->status, [self::STATUS_IMPORTED, self::STATUS_DISMISSED], true)) {
            return;
        }

        $status = $blockReason ? self::STATUS_BLOCKED : self::STATUS_PENDING;

        DB::table('migration_loan_inbox')->updateOrInsert(
            ['legacy_loan_id' => $legacyLoanId],
            [
                'legacy_user_id' => $legacyUserId,
                'status' => $status,
                'block_reason' => $blockReason,
                'raw_snapshot' => json_encode($legacyLoan),
                'detected_at' => $existing?->detected_at ?? now(),
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );
    }

    public function countPending(): int
    {
        return (int) DB::table('migration_loan_inbox')
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePending(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_loan_inbox')->orderByDesc('detected_at');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_BLOCKED]);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('legacy_loan_id', 'like', "%{$search}%")
                    ->orWhere('legacy_user_id', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findByLegacyLoanId(int $legacyLoanId): ?object
    {
        return DB::table('migration_loan_inbox')->where('legacy_loan_id', $legacyLoanId)->first();
    }

    public function markImported(int $legacyLoanId, int $adminId, int $mappedLoanId, ?string $notes = null): void
    {
        DB::table('migration_loan_inbox')
            ->where('legacy_loan_id', $legacyLoanId)
            ->update([
                'status' => self::STATUS_IMPORTED,
                'imported_at' => now(),
                'imported_by_admin_id' => $adminId,
                'mapped_loan_id' => $mappedLoanId,
                'import_notes' => $notes,
                'updated_at' => now(),
            ]);
    }

    public function markDismissed(int $legacyLoanId, int $adminId, ?string $notes = null): void
    {
        DB::table('migration_loan_inbox')
            ->where('legacy_loan_id', $legacyLoanId)
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
    public function pendingLegacyLoanIds(int $limit = 200): Collection
    {
        return DB::table('migration_loan_inbox')
            ->where('status', self::STATUS_PENDING)
            ->orderBy('legacy_loan_id')
            ->limit($limit)
            ->pluck('legacy_loan_id');
    }
}
