<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use Illuminate\Support\Facades\DB;

class LegacyLoanPollService
{
    public function __construct(
        private readonly MigrationLoanInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * @return array{detected: int, skipped_mapped: int, skipped_watermark: int}
     */
    public function poll(): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $watermark = config('legacy-parallel-run.loan_watermark_id');
        $limit = (int) config('legacy-parallel-run.poll_batch_limit', 200);

        $query = $legacy->table('loans')
            ->where('status_code', '301')
            ->orderBy('id');

        if ($watermark !== null) {
            $query->where('id', '>', $watermark);
        }

        $rows = $query->limit($limit)->get();

        $mappedIds = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
            ->pluck('legacy_identifier')
            ->flip()
            ->all();

        $stats = [
            'detected' => 0,
            'skipped_mapped' => 0,
            'skipped_watermark' => 0,
        ];

        foreach ($rows as $row) {
            $loan = (array) $row;
            $legacyLoanId = (int) $loan['id'];

            if (isset($mappedIds[(string) $legacyLoanId])) {
                $stats['skipped_mapped']++;

                continue;
            }

            $this->inbox->upsertDetected(
                $legacyLoanId,
                (int) $loan['user_id'],
                $loan,
            );
            $stats['detected']++;
        }

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_LOAN_POLL_AT);

        return $stats;
    }
}
