<?php

namespace App\Migration\ParallelRun;

class LegacyExpenseSyncService
{
    public function __construct(
        private readonly MigrationExpenseInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
        private readonly ParallelRunExpenseImportService $expenseImport,
    ) {}

    /**
     * @return array{processed: int, synced: int, failed: int, skipped: int}
     */
    public function syncPending(?int $limit = null): array
    {
        $limit ??= (int) config('legacy-parallel-run.expense_sync_batch_limit', 100);
        $rows = $this->inbox->pendingRows($limit);

        $stats = [
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $stats['processed']++;
            $legacyExpenseId = (int) $row->legacy_expense_id;
            $snapshot = json_decode($row->raw_snapshot ?? '{}', true) ?: [];

            try {
                $result = $this->expenseImport->importLegacyExpense($snapshot, applyBalanceUpdate: true);
                $status = $result['status'] ?? 'skipped';

                if (in_array($status, ['created', 'matched'], true)) {
                    $this->inbox->markSynced(
                        $legacyExpenseId,
                        isset($result['transaction_id']) ? (int) $result['transaction_id'] : null,
                    );
                    $stats['synced']++;
                } elseif ($status === 'skipped') {
                    $this->inbox->markSkipped($legacyExpenseId, $result['message'] ?? 'Skipped');
                    $stats['skipped']++;
                } else {
                    $this->inbox->markFailed($legacyExpenseId, $result['message'] ?? 'Unknown import result');
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $this->inbox->markFailed($legacyExpenseId, $e->getMessage());
                $stats['failed']++;
            }
        }

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_EXPENSE_SYNC_AT);

        return $stats;
    }
}
