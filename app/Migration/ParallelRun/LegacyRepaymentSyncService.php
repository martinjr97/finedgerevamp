<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\RepaymentMigrator;
use App\Migration\Replay\LegacyRepaymentReplayService;
use App\Migration\RepaymentAttributionService;
use App\Models\Repayment;
use App\Services\LoanPortfolioMaintenanceService;
use Illuminate\Support\Facades\DB;

class LegacyRepaymentSyncService
{
    public function __construct(
        private readonly MigrationRepaymentInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
        private readonly LegacyRepaymentReplayService $replayService,
        private readonly RepaymentMigrator $repaymentMigrator,
        private readonly LoanPortfolioMaintenanceService $portfolioMaintenance,
        private readonly ParallelRunRepaymentFinanceService $repaymentFinance,
    ) {}

    /**
     * @return array{processed: int, synced: int, failed: int, skipped: int}
     */
    public function syncPending(?int $limit = null): array
    {
        $limit ??= (int) config('legacy-parallel-run.repayment_sync_batch_limit', 100);
        $rows = $this->inbox->pendingRows($limit);

        $stats = [
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $usersReplayed = [];

        foreach ($rows as $row) {
            $stats['processed']++;
            $legacyUserId = (int) $row->legacy_user_id;
            $legacyRepaymentId = (int) $row->legacy_repayment_id;

            try {
                if (! isset($usersReplayed[$legacyUserId])) {
                    $replay = $this->replayService->dryRun(null, null, [$legacyUserId]);
                    $usersReplayed[$legacyUserId] = (int) $replay['migration_run_id'];
                }

                $replayRunId = $usersReplayed[$legacyUserId];

                $staged = DB::table('migration_repayments')
                    ->where('migration_run_id', $replayRunId)
                    ->where('legacy_repayment_id', $legacyRepaymentId)
                    ->first();

                if (! $staged) {
                    $this->inbox->markSkipped($legacyRepaymentId, 'Repayment not staged by replay engine');

                    $stats['skipped']++;

                    continue;
                }

                $class = (string) $staged->attribution_class;

                if (in_array($class, [RepaymentAttributionService::C_AMBIGUOUS, RepaymentAttributionService::D_MANUAL], true)) {
                    $this->inbox->markSkipped($legacyRepaymentId, "Attribution class {$class} requires manual review", $class);
                    $stats['skipped']++;

                    continue;
                }

                $promoteStats = $this->repaymentMigrator->run(
                    promote: true,
                    legacyUserId: $legacyUserId,
                    replayRunId: $replayRunId,
                    legacyRepaymentIds: [$legacyRepaymentId],
                );

                if (($promoteStats['promoted'] ?? 0) > 0) {
                    $mappedId = DB::table('migration_entity_maps')
                        ->where('entity_type', MigrationEntityMapRepository::TYPE_REPAYMENT)
                        ->where('legacy_identifier', (string) $legacyRepaymentId)
                        ->value('target_id');

                    $this->postRepaymentFinance($legacyRepaymentId);

                    $this->inbox->markSynced($legacyRepaymentId, $mappedId ? (int) $mappedId : null, $class);
                    $stats['synced']++;
                } else {
                    $this->inbox->markFailed($legacyRepaymentId, 'Promote returned zero rows', $class);
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $this->inbox->markFailed($legacyRepaymentId, $e->getMessage());
                $stats['failed']++;
            }
        }

        $this->refreshAgingForAffectedUsers($rows->pluck('legacy_user_id')->unique()->all());

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_REPAYMENT_SYNC_AT);

        return $stats;
    }

    private function postRepaymentFinance(int $legacyRepaymentId): void
    {
        $repayment = Repayment::query()
            ->where('external_reference', 'LEG-R-'.$legacyRepaymentId)
            ->first();

        if (! $repayment) {
            return;
        }

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $legacyRepayment = (array) $legacy->table('repayments')->where('id', $legacyRepaymentId)->first();
        } catch (\Throwable) {
            return;
        }

        if ($legacyRepayment === []) {
            return;
        }

        $this->repaymentFinance->postCollectionFromLegacy($repayment, $legacyRepayment);
    }

    /**
     * @param  list<int|string>  $legacyUserIds
     */
    private function refreshAgingForAffectedUsers(array $legacyUserIds): void
    {
        foreach ($legacyUserIds as $legacyUserId) {
            $customerMap = DB::table('migration_entity_maps')
                ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
                ->where('legacy_identifier', (string) $legacyUserId)
                ->first();

            if (! $customerMap) {
                continue;
            }

            $loans = \App\Models\Loan::query()
                ->where('customer_id', (int) $customerMap->target_id)
                ->activePortfolio()
                ->get();

            foreach ($loans as $loan) {
                $this->portfolioMaintenance->refreshScheduleAging($loan);
            }
        }
    }
}
