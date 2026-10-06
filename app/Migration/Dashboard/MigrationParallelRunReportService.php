<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\ParallelRun\MigrationLoanInboxRepository;
use App\Migration\ParallelRun\MigrationRepaymentInboxRepository;
use App\Migration\ParallelRun\MigrationSyncState;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationParallelRunReportService
{
    public function __construct(
        private readonly MigrationLoanInboxRepository $loanInbox,
        private readonly MigrationRepaymentInboxRepository $repaymentInbox,
        private readonly MigrationSyncState $syncState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'pending_loans' => $this->loanInbox->countPending(),
            'pending_repayments' => $this->repaymentInbox->countPending(),
            'failed_repayments' => (int) DB::table('migration_repayment_inbox')->where('status', MigrationRepaymentInboxRepository::STATUS_FAILED)->count(),
            'imported_loans' => (int) DB::table('migration_loan_inbox')->where('status', MigrationLoanInboxRepository::STATUS_IMPORTED)->count(),
            'last_loan_poll_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_LOAN_POLL_AT),
            'last_repayment_poll_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_REPAYMENT_POLL_AT),
            'last_repayment_sync_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_REPAYMENT_SYNC_AT),
            'polling_enabled' => (bool) config('legacy-parallel-run.enabled'),
            'loan_polling_enabled' => (bool) config('legacy-parallel-run.loan_polling_enabled'),
            'repayment_polling_enabled' => (bool) config('legacy-parallel-run.repayment_polling_enabled'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePendingLoans(array $filters = []): LengthAwarePaginator
    {
        return $this->loanInbox->paginatePending($filters);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingLoanDetail(int $legacyLoanId): ?array
    {
        $inbox = $this->loanInbox->findByLegacyLoanId($legacyLoanId);
        if (! $inbox) {
            return null;
        }

        $snapshot = json_decode($inbox->raw_snapshot ?? '{}', true) ?: [];

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $live = (array) $legacy->table('loans')->where('id', $legacyLoanId)->first();
        } catch (\Throwable) {
            $live = [];
        }

        return [
            'inbox' => $inbox,
            'snapshot' => $snapshot,
            'live' => $live,
        ];
    }
}
