<?php

namespace App\Migration;

use App\Models\Loan;
use App\Services\LoanRepaymentLedgerService;
use Illuminate\Support\Facades\DB;

class M1ReconciliationService
{
    public function __construct(
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
        private readonly LoanRepaymentLedgerService $ledgerService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reconcileRun(int $migrationRunId): array
    {
        $legacy = LegacyConnection::connection();
        $rows = DB::table('migration_loans')->where('migration_run_id', $migrationRunId)->get();
        $loanResults = [];
        $maxVariance = ['legacy_loan_id' => null, 'variance' => 0.0];
        $allPass = true;

        foreach ($rows as $row) {
            if ($row->migration_status === 'skipped_settled_support_only') {
                continue;
            }

            $legacyLoan = (array) $legacy->table('loans')->where('id', $row->legacy_loan_id)->first();
            $legacyEffective = $this->balanceCalculator->effectiveOutstanding($legacyLoan);
            $targetOutstanding = null;
            $variance = null;
            $pass = null;

            if ($row->mapped_loan_id) {
                $loan = Loan::find($row->mapped_loan_id);
                if ($loan) {
                    // M1 snapshot reconciliation: compare imported outstanding to legacy effective.
                    // Full ledger replay from attributed repayments is a follow-up step.
                    $targetOutstanding = (float) $loan->outstanding_balance;
                    $variance = abs($legacyEffective - $targetOutstanding);
                    $pass = $variance <= 0.01;
                    if (! $pass) {
                        $allPass = false;
                    }
                    if ($variance > ($maxVariance['variance'] ?? 0)) {
                        $maxVariance = ['legacy_loan_id' => $row->legacy_loan_id, 'variance' => $variance];
                    }
                }
            }

            DB::table('migration_loans')->where('id', $row->id)->update([
                'legacy_effective_outstanding' => $legacyEffective,
                'target_outstanding' => $targetOutstanding,
                'balance_variance' => $variance,
                'migration_status' => $pass === false ? 'variance_failed' : ($row->migration_status === 'imported' ? 'reconciled' : $row->migration_status),
            ]);

            $loanResults[] = [
                'legacy_loan_id' => $row->legacy_loan_id,
                'legacy_effective' => round($legacyEffective, 2),
                'target_outstanding' => $targetOutstanding !== null ? round($targetOutstanding, 2) : null,
                'variance' => $variance !== null ? round($variance, 2) : null,
                'pass' => $pass,
            ];
        }

        $attribution = DB::table('migration_repayments')
            ->where('migration_run_id', $migrationRunId)
            ->selectRaw('attribution_class, COUNT(*) as cnt')
            ->groupBy('attribution_class')
            ->pluck('cnt', 'attribution_class')
            ->all();

        $ambiguousCount = (int) DB::table('migration_repayments')
            ->where('migration_run_id', $migrationRunId)
            ->where('attribution_class', RepaymentAttributionService::C_AMBIGUOUS)
            ->count();

        $customerExposure = $this->reconcileCustomerExposure($migrationRunId);

        return [
            'migration_run_id' => $migrationRunId,
            'loan_results' => $loanResults,
            'largest_variance' => $maxVariance,
            'all_loans_pass' => $allPass,
            'attribution' => $attribution,
            'ambiguous_repayments' => $ambiguousCount,
            'customer_exposure' => $customerExposure,
            'go' => $allPass && $ambiguousCount === 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reconcileCustomerExposure(int $migrationRunId): array
    {
        $legacy = LegacyConnection::connection();
        $groups = DB::table('migration_loans')
            ->where('migration_run_id', $migrationRunId)
            ->where('migration_status', '!=', 'skipped_settled_support_only')
            ->get()
            ->groupBy('legacy_user_id');

        $out = [];
        foreach ($groups as $userId => $loans) {
            $legacySum = 0.0;
            $targetSum = 0.0;
            foreach ($loans as $loanRow) {
                $legacyLoan = (array) $legacy->table('loans')->where('id', $loanRow->legacy_loan_id)->first();
                $legacySum += $this->balanceCalculator->effectiveOutstanding($legacyLoan);
                if ($loanRow->mapped_loan_id) {
                    $target = Loan::find($loanRow->mapped_loan_id);
                    if ($target) {
                        $targetSum += (float) $target->outstanding_balance;
                    }
                }
            }

            $accountBalance = $legacy->table('loans_accounts')->where('customer_id', $userId)->value('balance');

            $out[] = [
                'legacy_user_id' => $userId,
                'legacy_sum_effective' => round($legacySum, 2),
                'target_sum_outstanding' => round($targetSum, 2),
                'variance' => round(abs($legacySum - $targetSum), 2),
                'loans_accounts_balance_info' => $accountBalance !== null ? (float) $accountBalance : null,
            ];
        }

        return $out;
    }
}
