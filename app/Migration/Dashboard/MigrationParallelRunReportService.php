<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\Dashboard\MigrationCustomerDetailService;
use App\Migration\ParallelRun\MigrationCustomerInboxRepository;
use App\Migration\ParallelRun\MigrationExpenseInboxRepository;
use App\Migration\ParallelRun\MigrationLoanInboxRepository;
use App\Migration\ParallelRun\MigrationRepaymentInboxRepository;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\ParallelRun\MigrationSyncState;
use App\Migration\ParallelRun\PendingLoanImportPreviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationParallelRunReportService
{
    public function __construct(
        private readonly MigrationLoanInboxRepository $loanInbox,
        private readonly MigrationCustomerInboxRepository $customerInbox,
        private readonly MigrationRepaymentInboxRepository $repaymentInbox,
        private readonly MigrationExpenseInboxRepository $expenseInbox,
        private readonly MigrationSyncState $syncState,
        private readonly PendingLoanImportPreviewService $importPreview,
        private readonly MigrationCustomerDetailService $customerDetails,
        private readonly MigrationEntityMapRepository $maps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'pending_loans' => $this->loanInbox->countPending(),
            'pending_customers' => $this->customerInbox->countPending(),
            'pending_repayments' => $this->repaymentInbox->countPending(),
            'pending_expenses' => $this->expenseInbox->countPending(),
            'failed_repayments' => (int) DB::table('migration_repayment_inbox')->where('status', MigrationRepaymentInboxRepository::STATUS_FAILED)->count(),
            'failed_expenses' => (int) DB::table('migration_expense_inbox')->where('status', MigrationExpenseInboxRepository::STATUS_FAILED)->count(),
            'imported_loans' => (int) DB::table('migration_loan_inbox')->where('status', MigrationLoanInboxRepository::STATUS_IMPORTED)->count(),
            'last_loan_poll_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_LOAN_POLL_AT),
            'last_repayment_poll_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_REPAYMENT_POLL_AT),
            'last_repayment_sync_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_REPAYMENT_SYNC_AT),
            'last_expense_poll_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_EXPENSE_POLL_AT),
            'last_expense_sync_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_EXPENSE_SYNC_AT),
            'last_customer_poll_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_CUSTOMER_POLL_AT),
            'last_customer_sync_at' => $this->syncState->get(MigrationSyncState::KEY_LAST_CUSTOMER_SYNC_AT),
            'polling_enabled' => (bool) config('legacy-parallel-run.enabled'),
            'loan_polling_enabled' => (bool) config('legacy-parallel-run.loan_polling_enabled'),
            'customer_polling_enabled' => (bool) config('legacy-parallel-run.customer_polling_enabled'),
            'repayment_polling_enabled' => (bool) config('legacy-parallel-run.repayment_polling_enabled'),
            'expense_polling_enabled' => (bool) config('legacy-parallel-run.expense_polling_enabled'),
            'finance_on_import_enabled' => (bool) config('legacy-parallel-run.finance_on_import_enabled'),
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
     * @param  array<string, mixed>  $filters
     */
    public function paginatePendingCustomers(array $filters = []): LengthAwarePaginator
    {
        return $this->customerInbox->paginatePending($filters);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pendingCustomerDetail(int $legacyUserId): ?array
    {
        $inbox = $this->customerInbox->findByLegacyUserId($legacyUserId);
        if (! $inbox) {
            return null;
        }

        $snapshot = json_decode($inbox->raw_snapshot ?? '{}', true) ?: [];
        $context = json_decode($inbox->context ?? '{}', true) ?: [];

        $staging = DB::table('migration_customers')
            ->where('legacy_user_id', $legacyUserId)
            ->orderByDesc('id')
            ->first();

        $customerDetail = $this->customerDetails->build($legacyUserId, $staging);

        $customerMap = $this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $legacyUserId);

        return [
            'inbox' => $inbox,
            'snapshot' => $snapshot,
            'context' => $context,
            'customer_detail' => $customerDetail,
            'customer_map' => $customerMap,
            'can_promote' => ! $customerMap && in_array($inbox->status, [
                MigrationCustomerInboxRepository::STATUS_PENDING,
                MigrationCustomerInboxRepository::STATUS_BLOCKED,
            ], true),
        ];
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

        $preview = $this->importPreview->build($legacyLoanId, $inbox, $snapshot, $live);

        return [
            'inbox' => $inbox,
            'snapshot' => $snapshot,
            'live' => $live,
            'preview' => $preview,
        ];
    }
}
