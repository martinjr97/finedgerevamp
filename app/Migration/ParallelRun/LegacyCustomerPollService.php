<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\MigrationEntityMapRepository;
use Illuminate\Support\Facades\DB;

class LegacyCustomerPollService
{
    public function __construct(
        private readonly MigrationCustomerInboxRepository $inbox,
        private readonly MigrationSyncState $syncState,
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * Detect legacy borrowers who need customer migration (parallel-run / post-cutover).
     *
     * @return array{detected: int, skipped_mapped: int, skipped_no_customer_row: int}
     */
    public function poll(): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $watermark = config('legacy-parallel-run.loan_watermark_id');
        $limit = (int) config('legacy-parallel-run.poll_batch_limit', 200);

        $mappedUserIds = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->pluck('legacy_identifier')
            ->flip()
            ->all();

        $candidateUserIds = [];

        $loanQuery = $legacy->table('loans')
            ->where('status_code', '301')
            ->orderBy('id');

        if ($watermark !== null) {
            $loanQuery->where('id', '>', $watermark);
        }

        foreach ($loanQuery->limit($limit)->get() as $loanRow) {
            $userId = (int) $loanRow->user_id;
            if ($userId > 0) {
                $candidateUserIds[$userId] = true;
            }
        }

        $inboxUserIds = DB::table('migration_loan_inbox')
            ->whereIn('status', [
                MigrationLoanInboxRepository::STATUS_PENDING,
                MigrationLoanInboxRepository::STATUS_BLOCKED,
            ])
            ->distinct()
            ->pluck('legacy_user_id');

        foreach ($inboxUserIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $candidateUserIds[$userId] = true;
            }
        }

        $stats = [
            'detected' => 0,
            'skipped_mapped' => 0,
            'skipped_no_customer_row' => 0,
        ];

        foreach (array_keys($candidateUserIds) as $legacyUserId) {
            if (isset($mappedUserIds[(string) $legacyUserId])) {
                $stats['skipped_mapped']++;

                continue;
            }

            $legacyCustomer = (array) $legacy->table('customers')->where('user_id', $legacyUserId)->first();
            if ($legacyCustomer === []) {
                $stats['skipped_no_customer_row']++;

                continue;
            }

            $legacyUser = (array) $legacy->table('users')->where('id', $legacyUserId)->first();

            $context = [
                'source' => 'parallel_run_customer_poll',
                'legacy_loan_ids' => $legacy->table('loans')
                    ->where('user_id', $legacyUserId)
                    ->where('status_code', '301')
                    ->when($watermark !== null, fn ($q) => $q->where('id', '>', $watermark))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ];

            $snapshot = array_merge($legacyCustomer, [
                'legacy_user' => $legacyUser,
            ]);

            $this->inbox->upsertDetected($legacyUserId, $snapshot, $context);
            $stats['detected']++;
        }

        $this->syncState->touchNow(MigrationSyncState::KEY_LAST_CUSTOMER_POLL_AT);

        return $stats;
    }
}
