<?php

namespace App\Migration\ParallelRun;

use App\Migration\LegacyConnection;
use App\Migration\Phases\ActiveLoanMigrator;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\RepaymentMigrator;
use App\Migration\Replay\LegacyRepaymentReplayService;
use App\Models\Loan;
use App\Models\Repayment;
use App\Services\LoanPortfolioMaintenanceService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ParallelRunLoanImportService
{
    public function __construct(
        private readonly MigrationLoanInboxRepository $inbox,
        private readonly ActiveLoanMigrator $activeLoanMigrator,
        private readonly LegacyRepaymentReplayService $replayService,
        private readonly RepaymentMigrator $repaymentMigrator,
        private readonly LegacyRepaymentSyncService $repaymentSync,
        private readonly LoanPortfolioMaintenanceService $portfolioMaintenance,
        private readonly MigrationEntityMapRepository $maps,
        private readonly ParallelRunLoanFinanceService $loanFinance,
        private readonly ParallelRunRepaymentFinanceService $repaymentFinance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function importConfirmed(int $legacyLoanId, int $adminId): array
    {
        $inboxRow = $this->inbox->findByLegacyLoanId($legacyLoanId);
        if ($inboxRow && $inboxRow->status === MigrationLoanInboxRepository::STATUS_IMPORTED) {
            throw new RuntimeException("Legacy loan {$legacyLoanId} was already imported.");
        }

        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();
        $legacyLoan = (array) $legacy->table('loans')->where('id', $legacyLoanId)->first();
        if (! $legacyLoan || (string) ($legacyLoan['status_code'] ?? '') !== '301') {
            throw new RuntimeException("Legacy loan {$legacyLoanId} is not active (status 301).");
        }

        $legacyUserId = (int) $legacyLoan['user_id'];

        $loanSummary = $this->activeLoanMigrator->run(
            promote: true,
            legacyLoanId: $legacyLoanId,
        );

        if (($loanSummary['created'] ?? 0) === 0 && ($loanSummary['matched_existing'] ?? 0) === 0) {
            $reason = $this->describeLoanImportBlock($loanSummary);
            if ($inboxRow) {
                DB::table('migration_loan_inbox')
                    ->where('legacy_loan_id', $legacyLoanId)
                    ->update([
                        'status' => MigrationLoanInboxRepository::STATUS_BLOCKED,
                        'block_reason' => $reason,
                        'updated_at' => now(),
                    ]);
            }

            throw new RuntimeException($reason);
        }

        $map = $this->maps->find(MigrationEntityMapRepository::TYPE_LOAN, (string) $legacyLoanId);
        if (! $map) {
            throw new RuntimeException("Loan map missing after import for legacy loan {$legacyLoanId}.");
        }

        $loan = Loan::query()->findOrFail((int) $map->target_id);

        $loanFinanceSummary = $this->loanFinance->postDisbursementFromLegacy($loan, $legacyLoanId);

        $replay = $this->replayService->dryRun(null, null, [$legacyUserId]);
        $repaymentSummary = $this->repaymentMigrator->run(
            promote: true,
            legacyUserId: $legacyUserId,
            replayRunId: (int) $replay['migration_run_id'],
        );

        $repaymentFinanceSummary = $this->postFinanceForLegacyUserRepayments($legacy, $legacyUserId);

        $accrualDays = $this->portfolioMaintenance->catchUpDailyAccrual($loan);
        $schedulesRefreshed = $this->portfolioMaintenance->refreshScheduleAging($loan);

        $this->inbox->markImported(
            $legacyLoanId,
            $adminId,
            (int) $loan->id,
            json_encode([
                'loan_summary' => $loanSummary,
                'loan_finance_summary' => $loanFinanceSummary,
                'repayment_summary' => $repaymentSummary,
                'repayment_finance_summary' => $repaymentFinanceSummary,
                'accrual_days_caught_up' => $accrualDays,
                'schedules_refreshed' => $schedulesRefreshed,
            ])
        );

        return [
            'legacy_loan_id' => $legacyLoanId,
            'target_loan_id' => $loan->id,
            'loan_summary' => $loanSummary,
            'loan_finance_summary' => $loanFinanceSummary,
            'repayment_summary' => $repaymentSummary,
            'repayment_finance_summary' => $repaymentFinanceSummary,
            'accrual_days_caught_up' => $accrualDays,
            'schedules_refreshed' => $schedulesRefreshed,
        ];
    }

    /**
     * @return array{posted: int, skipped: int, already_posted: int}
     */
    private function postFinanceForLegacyUserRepayments($legacy, int $legacyUserId): array
    {
        $stats = ['posted' => 0, 'skipped' => 0, 'already_posted' => 0];

        $legacyRepayments = $legacy->table('repayments')
            ->where('user_id', $legacyUserId)
            ->where('status_code', 215)
            ->get();

        foreach ($legacyRepayments as $legacyRepaymentRow) {
            $legacyRepayment = (array) $legacyRepaymentRow;
            $legacyRepaymentId = (int) ($legacyRepayment['id'] ?? 0);
            if ($legacyRepaymentId < 1) {
                continue;
            }

            $repayment = Repayment::query()
                ->where('external_reference', 'LEG-R-'.$legacyRepaymentId)
                ->first();

            if (! $repayment) {
                $stats['skipped']++;

                continue;
            }

            $result = $this->repaymentFinance->postCollectionFromLegacy($repayment, $legacyRepayment);
            match ($result['status'] ?? 'skipped') {
                'posted' => $stats['posted']++,
                'already_posted' => $stats['already_posted']++,
                default => $stats['skipped']++,
            };
        }

        return $stats;
    }

    public function dismiss(int $legacyLoanId, int $adminId, ?string $notes = null): void
    {
        $this->inbox->markDismissed($legacyLoanId, $adminId, $notes);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function describeLoanImportBlock(array $summary): string
    {
        if (($summary['manual_review'] ?? 0) > 0) {
            return 'Loan is in manual review cohort and cannot be auto-imported.';
        }
        if (($summary['blocked_missing_customer'] ?? 0) > 0) {
            return 'Customer is not mapped — migrate or map the customer first.';
        }
        if (($summary['blocked'] ?? 0) > 0) {
            return 'Loan import blocked (missing product or blocked cohort).';
        }

        return 'Loan import did not create or match a target loan.';
    }
}
