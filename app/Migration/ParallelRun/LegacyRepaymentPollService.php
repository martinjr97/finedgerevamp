<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use Illuminate\Support\Facades\DB;

class LegacyRepaymentPollService
{
    public function __construct(
        private readonly MigrationRepaymentInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
    ) {}

    /**
     * Detect legacy repayments for customers with at least one mapped loan.
     *
     * @return array{detected: int, skipped_mapped: int, skipped_unmapped_customer: int}
     */
    public function poll(): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $mappedUserIds = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->pluck('legacy_identifier')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($mappedUserIds === []) {
            $this->syncState->touchNow(MigrationSyncState::KEY_LAST_REPAYMENT_POLL_AT);

            return [
                'detected' => 0,
                'skipped_mapped' => 0,
                'skipped_unmapped_customer' => 0,
            ];
        }

        $mappedRepaymentIds = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_REPAYMENT)
            ->pluck('legacy_identifier')
            ->flip()
            ->all();

        $limit = (int) config('legacy-parallel-run.poll_batch_limit', 200);
        $lastPoll = $this->syncState->getTimestamp(MigrationSyncState::KEY_LAST_REPAYMENT_POLL_AT);

        $query = $legacy->table('repayments')
            ->where('status_code', 215)
            ->whereIn('user_id', $mappedUserIds)
            ->orderBy('id');

        if ($lastPoll !== null) {
            $query->where('created_at', '>=', $lastPoll->copy()->subHour());
        }

        $rows = $query->limit($limit)->get();

        $stats = [
            'detected' => 0,
            'skipped_mapped' => 0,
            'skipped_unmapped_customer' => 0,
        ];

        foreach ($rows as $row) {
            $repayment = (array) $row;
            $legacyRepaymentId = (int) $repayment['id'];

            if (isset($mappedRepaymentIds[(string) $legacyRepaymentId])) {
                $stats['skipped_mapped']++;

                continue;
            }

            $this->inbox->upsertDetected(
                $legacyRepaymentId,
                (int) $repayment['user_id'],
                $repayment,
            );
            $stats['detected']++;
        }

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_REPAYMENT_POLL_AT);

        return $stats;
    }
}
