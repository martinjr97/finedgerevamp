<?php

namespace App\Migration\Replay;

use App\Migration\LegacyConnection;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Migration\LegacyProductMapper;
use App\Migration\RepaymentAttributionService;
use App\Migration\Replay\DTOs\ReplayAllocation;
use App\Migration\Replay\DTOs\ReplayResult;
use App\Migration\Replay\Strategies\CharacterReplayStrategy;
use App\Migration\Replay\Strategies\MarketizeReplayStrategy;
use App\Migration\Replay\Strategies\SalaryBasedClientReplayStrategy;
use App\Migration\Replay\Support\LegacyRepaymentContext;
use Illuminate\Support\Facades\DB;

class LegacyRepaymentReplayService
{
    public function __construct(
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
        private readonly LegacyProductMapper $productMapper,
        private readonly RepaymentAttributionService $attribution,
        private readonly LegacyRepaymentContext $context,
        private readonly MarketizeReplayStrategy $marketizeStrategy,
        private readonly SalaryBasedClientReplayStrategy $accrualStrategy,
        private readonly CharacterReplayStrategy $characterStrategy,
    ) {}

    /**
     * @param  list<int>|null  $activeLoanIds  limit to specific legacy loan ids
     * @return array<string, mixed>
     */
    public function dryRun(?array $activeLoanIds = null, ?string $productFilter = null): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $db = LegacyConnection::connection();

        $activeLoansQuery = $db->table('loans')->where('status_code', '301');
        if ($activeLoanIds) {
            $activeLoansQuery->whereIn('id', $activeLoanIds);
        }
        $activeLoans = $activeLoansQuery->get()->map(fn ($r) => (array) $r);
        $userIds = $activeLoans->pluck('user_id')->unique()->values()->all();

        $runId = DB::table('migration_runs')->insertGetId([
            'name' => 'm1-replay-dry-run-'.now()->format('Ymd-His'),
            'phase' => 'm1.1',
            'scope' => $activeLoanIds ? 'subset' : 'active-752',
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('migration_repayment_allocations')->where('migration_run_id', $runId)->delete();
        DB::table('migration_loan_replay_results')->where('migration_run_id', $runId)->delete();
        DB::table('migration_customer_replay_results')->where('migration_run_id', $runId)->delete();

        $attributionCounts = [
            RepaymentAttributionService::A_DIRECT => 0,
            RepaymentAttributionService::B_RECONSTRUCTED => 0,
            RepaymentAttributionService::C_AMBIGUOUS => 0,
            RepaymentAttributionService::D_MANUAL => 0,
        ];

        $ambiguousCases = [];
        $sourceRepaymentTotal = 0.0;
        $stagedRepaymentTotal = 0.0;
        $allocationTotal = 0.0;
        $finalLoanStatesByUser = [];

        $activeLoanIdSet = $activeLoans->pluck('id')->flip()->all();

        foreach ($userIds as $userId) {
            $customer = (array) $db->table('customers')->where('user_id', $userId)->first();
            $client = $customer
                ? (array) $db->table('clients')->where('id', $customer['client_id'])->first()
                : null;

            $allUserLoans = $db->table('loans')->where('user_id', $userId)->orderBy('id')->get()->map(fn ($r) => (array) $r);
            $loanStates = [];
            foreach ($allUserLoans as $loan) {
                $isAccrual = $this->balanceCalculator->isAccrualLoan($loan);
                $loanStates[(int) $loan['id']] = array_merge($loan, [
                    'status' => (string) $loan['status_code'],
                    'settled_before_payment' => false,
                    'repaid_amount' => 0,
                    'current_loan_amount' => $isAccrual
                        ? (float) ($loan['loan_amount'] ?? 0)
                        : ($loan['current_loan_amount'] ?? null),
                ]);
            }

            $repayments = $db->table('repayments')
                ->where('user_id', $userId)
                ->where('status_code', 215)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn ($r) => (array) $r);

            foreach ($repayments as $repayment) {
                $paymentAt = (string) $repayment['created_at'];
                foreach ($loanStates as &$state) {
                    if (($state['settled_date'] ?? null) && $state['settled_date'] <= $paymentAt) {
                        $state['settled_before_payment'] = true;
                        $state['status'] = '300';
                    }
                }
                unset($state);

                $strategy = $this->resolveStrategy($repayment, $customer, $client);
                $result = $strategy->replay($repayment, $customer, $client, $loanStates);

                $attributionCounts[$result->classification] = ($attributionCounts[$result->classification] ?? 0) + 1;
                $amount = (float) $repayment['repayment_amount'];
                $sourceRepaymentTotal += $amount;
                $stagedRepaymentTotal += $amount;

                if ($result->classification === RepaymentAttributionService::C_AMBIGUOUS) {
                    $ambiguousCases[] = $this->buildAmbiguousCase($repayment, $customer, $loanStates, $paymentAt, $result);
                }

                DB::table('migration_repayments')->updateOrInsert(
                    ['migration_run_id' => $runId, 'legacy_repayment_id' => $repayment['id']],
                    [
                        'legacy_user_id' => $userId,
                        'attribution_class' => $result->classification,
                        'repayment_amount' => $amount,
                        'migration_status' => $result->classification === RepaymentAttributionService::C_AMBIGUOUS ? 'manual_review' : 'staged',
                        'confidence' => $result->confidence,
                        'exception' => $result->exception,
                        'allocations' => json_encode(array_map(fn (ReplayAllocation $a) => $a->toArray(), $result->allocations)),
                        'raw_context' => json_encode($result->rawContext),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                foreach ($result->allocations as $allocation) {
                    if (! isset($activeLoanIdSet[$allocation->legacyLoanId])) {
                        continue;
                    }
                    $allocationTotal += $allocation->allocatedAmount;
                    DB::table('migration_repayment_allocations')->insert([
                        'migration_run_id' => $runId,
                        'legacy_repayment_id' => $allocation->legacyRepaymentId,
                        'legacy_loan_id' => $allocation->legacyLoanId,
                        'legacy_user_id' => $allocation->legacyUserId,
                        'allocated_amount' => $allocation->allocatedAmount,
                        'principal_amount' => $allocation->principalAmount,
                        'interest_amount' => $allocation->interestAmount,
                        'fee_amount' => $allocation->feeAmount,
                        'penalty_amount' => $allocation->penaltyAmount,
                        'classification' => $allocation->classification,
                        'confidence' => $allocation->confidence,
                        'rule_used' => $allocation->ruleUsed,
                        'balance_before' => $allocation->balanceBefore,
                        'balance_after' => $allocation->balanceAfter,
                        'raw_context' => json_encode($allocation->rawContext),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $finalLoanStatesByUser[$userId] = $loanStates;
        }

        $loanResults = [];
        $statusCounts = [
            'PASS' => 0,
            'PASS_WITH_MIGRATION_ADJUSTMENT' => 0,
            'MANUAL_REVIEW' => 0,
            'FAIL' => 0,
        ];
        $productStats = [];

        foreach ($activeLoans as $loan) {
            $loanId = (int) $loan['id'];
            $userId = (int) $loan['user_id'];
            $client = $db->table('clients')->where('id', $loan['client_id'])->first();
            $clientArr = $client ? (array) $client : null;
            $productClass = $this->resolveProductClass($loan, $clientArr);

            if ($productFilter && strtolower($productClass) !== strtolower($productFilter)) {
                continue;
            }

            $legacyEffective = $this->balanceCalculator->effectiveOutstanding($loan);
            $cash = (float) DB::table('migration_repayment_allocations')
                ->where('migration_run_id', $runId)
                ->where('legacy_loan_id', $loanId)
                ->sum('allocated_amount');

            $principal = (float) DB::table('migration_repayment_allocations')
                ->where('migration_run_id', $runId)
                ->where('legacy_loan_id', $loanId)
                ->sum('principal_amount');

            $interest = (float) DB::table('migration_repayment_allocations')
                ->where('migration_run_id', $runId)
                ->where('legacy_loan_id', $loanId)
                ->sum('interest_amount');

            $isAccrual = $this->balanceCalculator->isAccrualLoan($loan);
            $simulated = $isAccrual
                ? $this->simulatedAccrualFromState($finalLoanStatesByUser[$userId][$loanId] ?? $loan)
                : max(0, (float) ($loan['loan_amount'] ?? 0) - $cash);

            $adjustment = round($legacyEffective - $simulated, 2);
            $reconstructed = round($simulated + $adjustment, 2);
            $variance = abs(round($legacyEffective - $reconstructed, 2));

            $hasAmbiguous = $this->loanHadAmbiguousRepayment($runId, $loanId);

            if ($hasAmbiguous) {
                $reconciliation = 'MANUAL_REVIEW';
                $promotion = 'MANUAL_REVIEW';
            } elseif ($variance <= 0.01 && abs($adjustment) <= 0.01) {
                $reconciliation = 'PASS';
                $promotion = 'PROMOTABLE';
            } elseif ($variance <= 0.01 && abs($adjustment) > 0.01) {
                $reconciliation = 'PASS_WITH_MIGRATION_ADJUSTMENT';
                $promotion = 'PROMOTABLE';
            } else {
                $reconciliation = 'FAIL';
                $promotion = 'BLOCKED';
            }

            $statusCounts[$reconciliation]++;
            $this->initProductStats($productStats, $productClass);
            $productStats[$productClass]['loans']++;
            $productStats[$productClass]['legacy_outstanding'] += $legacyEffective;
            $productStats[$productClass]['reconstructed_outstanding'] += $reconstructed;
            $productStats[$productClass]['variance'] += $variance;
            $productStats[$productClass][$reconciliation] = ($productStats[$productClass][$reconciliation] ?? 0) + 1;

            DB::table('migration_loan_replay_results')->insert([
                'migration_run_id' => $runId,
                'legacy_loan_id' => $loanId,
                'legacy_user_id' => $userId,
                'product_class' => $productClass,
                'legacy_effective_outstanding' => $legacyEffective,
                'replayed_cash_total' => $cash,
                'replayed_principal' => $principal,
                'replayed_interest' => $interest,
                'simulated_outstanding' => $simulated,
                'migration_opening_adjustment' => $adjustment,
                'reconstructed_outstanding' => $reconstructed,
                'variance' => $variance,
                'reconciliation_status' => $reconciliation,
                'promotion_status' => $promotion,
                'raw_context' => json_encode(['is_accrual' => $isAccrual]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $loanResults[] = compact('loanId', 'productClass', 'legacyEffective', 'simulated', 'adjustment', 'reconstructed', 'variance', 'reconciliation', 'promotion');
        }

        $customerResults = [];
        foreach ($userIds as $userId) {
            $legacySum = $activeLoans->where('user_id', $userId)->sum(fn ($l) => $this->balanceCalculator->effectiveOutstanding((array) $l));
            $reconstructedSum = (float) DB::table('migration_loan_replay_results')
                ->where('migration_run_id', $runId)
                ->where('legacy_user_id', $userId)
                ->sum('reconstructed_outstanding');
            $variance = abs(round($legacySum - $reconstructedSum, 2));
            $hasManual = DB::table('migration_loan_replay_results')
                ->where('migration_run_id', $runId)
                ->where('legacy_user_id', $userId)
                ->whereIn('reconciliation_status', ['MANUAL_REVIEW', 'FAIL'])
                ->exists();
            $custStatus = $hasManual ? 'MANUAL_REVIEW' : ($variance <= 0.01 ? 'PASS' : 'FAIL');

            DB::table('migration_customer_replay_results')->insert([
                'migration_run_id' => $runId,
                'legacy_user_id' => $userId,
                'legacy_sum_effective' => $legacySum,
                'reconstructed_sum' => $reconstructedSum,
                'variance' => $variance,
                'reconciliation_status' => $custStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $customerResults[] = ['user_id' => $userId, 'legacy_sum' => $legacySum, 'reconstructed_sum' => $reconstructedSum, 'variance' => $variance, 'status' => $custStatus];
        }

        $largestVariance = collect($loanResults)->sortByDesc('variance')->first();
        $accountAdjustments = $this->summarizeAccountAdjustments($userIds);

        $allocationConservationPass = $this->verifyAllocationConservation($runId);

        $summary = [
            'migration_run_id' => $runId,
            'attribution' => $attributionCounts,
            'loan_status' => $statusCounts,
            'product_stats' => $productStats,
            'conservation' => [
                'source_repayment_total' => round($sourceRepaymentTotal, 2),
                'staged_repayment_total' => round($stagedRepaymentTotal, 2),
                'allocation_total' => round($allocationTotal, 2),
                'repayment_conservation_pass' => abs($sourceRepaymentTotal - $stagedRepaymentTotal) <= 0.01,
                'allocation_conservation_pass' => $allocationConservationPass,
            ],
            'largest_variance' => $largestVariance,
            'ambiguous_cases' => $ambiguousCases,
            'account_adjustments' => $accountAdjustments,
            'promotable_loans' => $statusCounts['PASS'] + $statusCounts['PASS_WITH_MIGRATION_ADJUSTMENT'],
            'manual_or_blocked' => $statusCounts['MANUAL_REVIEW'] + $statusCounts['FAIL'],
            'customer_results' => $customerResults,
        ];

        DB::table('migration_runs')->where('id', $runId)->update([
            'status' => 'completed',
            'summary' => json_encode($summary),
            'completed_at' => now(),
        ]);

        return $summary;
    }

    private function resolveStrategy(array $repayment, ?array $customer, ?array $client): CharacterReplayStrategy|MarketizeReplayStrategy|SalaryBasedClientReplayStrategy
    {
        if ($this->marketizeStrategy->supports($repayment, $customer, $client)) {
            return $this->marketizeStrategy;
        }
        if ($this->accrualStrategy->supports($repayment, $customer, $client)) {
            return $this->accrualStrategy;
        }

        return $this->characterStrategy;
    }

    /**
     * @param  array<string, mixed>  $loan
     * @param  array<string, mixed>|null  $client
     */
    private function resolveProductClass(array $loan, ?array $client): string
    {
        if (($client['product_type'] ?? null) === 'marketize_based') {
            return 'Marketeer';
        }
        if ((bool) ($loan['gvnt_loan'] ?? false)) {
            return 'Government';
        }
        if ((bool) ($loan['salary_based'] ?? false) || ($client['product_type'] ?? null) === 'salary_based') {
            return 'MOU';
        }
        if (($client['product_type'] ?? null) === 'character_based') {
            return 'Character';
        }

        return 'Character';
    }

    /**
     * @param  array<string, mixed>  $loanState
     */
    private function simulatedAccrualFromState(array $loanState): float
    {
        return max(0, (float) ($loanState['current_loan_amount'] ?? 0));
    }

    private function loanHadAmbiguousRepayment(int $runId, int $loanId): bool
    {
        $ambiguous = DB::table('migration_repayments')
            ->where('migration_run_id', $runId)
            ->where('attribution_class', RepaymentAttributionService::C_AMBIGUOUS)
            ->get(['raw_context']);

        foreach ($ambiguous as $row) {
            $ctx = json_decode($row->raw_context ?? '[]', true);
            $eligible = $ctx['eligible_loan_ids'] ?? [];
            if (in_array($loanId, $eligible, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $loanStates
     */
    private function buildAmbiguousCase(array $repayment, ?array $customer, array $loanStates, string $paymentAt, ReplayResult $result): array
    {
        $eligible = array_values(array_filter($loanStates, fn ($s) => ($s['created_at'] ?? '') <= $paymentAt && ($s['status'] ?? '301') === '301'));

        return [
            'legacy_repayment_id' => $repayment['id'],
            'legacy_user_id' => $repayment['user_id'],
            'repayment_date' => $paymentAt,
            'amount' => (float) $repayment['repayment_amount'],
            'eligible_loan_ids' => array_column($eligible, 'id'),
            'affected_loan_ids' => $repayment['affected_loan_ids'] ?? null,
            'rule_used' => $result->ruleUsed,
            'exception' => $result->exception,
            'eligible_balances' => array_map(fn ($l) => [
                'loan_id' => $l['id'],
                'current_loan_amount' => $l['current_loan_amount'] ?? null,
                'loan_amount' => $l['loan_amount'] ?? null,
                'repaid_amount' => $l['repaid_amount'] ?? null,
            ], $eligible),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $productStats
     */
    private function initProductStats(array &$productStats, string $productClass): void
    {
        if (! isset($productStats[$productClass])) {
            $productStats[$productClass] = [
                'loans' => 0,
                'legacy_outstanding' => 0,
                'reconstructed_outstanding' => 0,
                'variance' => 0,
                'PASS' => 0,
                'PASS_WITH_MIGRATION_ADJUSTMENT' => 0,
                'MANUAL_REVIEW' => 0,
                'FAIL' => 0,
            ];
        }
    }

    private function verifyAllocationConservation(int $runId): bool
    {
        $repayments = DB::table('migration_repayments')
            ->where('migration_run_id', $runId)
            ->whereIn('attribution_class', [
                RepaymentAttributionService::A_DIRECT,
                RepaymentAttributionService::B_RECONSTRUCTED,
            ])
            ->get(['legacy_repayment_id', 'repayment_amount', 'allocations']);

        foreach ($repayments as $row) {
            $allocations = json_decode($row->allocations ?? '[]', true);
            if (! is_array($allocations)) {
                return false;
            }
            $sum = array_sum(array_column($allocations, 'allocated_amount'));
            if (abs((float) $row->repayment_amount - $sum) > 0.01) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<string, mixed>
     */
    private function summarizeAccountAdjustments(array $userIds): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $db = LegacyConnection::connection();

        $adjustments = $db->table('loan_account_balance_adjustments')
            ->whereIn('customer_id', $userIds)
            ->get()
            ->map(fn ($r) => (array) $r);

        return [
            'count_in_active_portfolio' => $adjustments->count(),
            'unallocated_flag' => 'ACCOUNT_ADJUSTMENT_UNALLOCATED',
            'note' => 'Adjustments are customer/account-level with no loan_id; not auto-distributed. Active-loan reconciliation uses loan effective balances, not loans_accounts.balance.',
            'sample_ids' => $adjustments->take(5)->pluck('id')->all(),
        ];
    }
}
