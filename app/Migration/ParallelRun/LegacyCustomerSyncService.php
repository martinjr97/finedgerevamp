<?php

namespace App\Migration\ParallelRun;

class LegacyCustomerSyncService
{
    public function __construct(
        private readonly MigrationCustomerInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
        private readonly ParallelRunCustomerPromoteService $promoteService,
    ) {}

    /**
     * @return array{processed: int, promoted: int, failed: int, skipped: int}
     */
    public function syncPending(?int $limit = null, ?int $adminId = null): array
    {
        $limit ??= (int) config('legacy-parallel-run.customer_sync_batch_limit', 50);
        $rows = $this->inbox->pendingLegacyUserIds($limit);

        $stats = [
            'processed' => 0,
            'promoted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $stats['processed']++;
            $legacyUserId = (int) $row->legacy_user_id;

            $result = $this->promoteService->promoteLegacyUser($legacyUserId);

            if ($result['success'] && $result['target_customer_id']) {
                $this->inbox->markImported(
                    $legacyUserId,
                    $adminId ?? 0,
                    (int) $result['target_customer_id'],
                    json_encode(['status' => $result['status'], 'summary' => $result['summary']])
                );
                $stats['promoted']++;

                continue;
            }

            if ($result['status'] === 'already_mapped' && $result['target_customer_id']) {
                $this->inbox->markImported(
                    $legacyUserId,
                    $adminId ?? 0,
                    (int) $result['target_customer_id'],
                    'already_mapped_on_sync'
                );
                $stats['promoted']++;

                continue;
            }

            $this->inbox->markBlocked($legacyUserId, $result['message']);
            $stats['failed']++;
        }

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_CUSTOMER_SYNC_AT);

        return $stats;
    }
}
