<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\Phases\ManualReviewCohorts;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\MigrationStatusService;
use App\Migration\Phases\Support\CustomerIdentityResolutionRegistry;
use App\Migration\RepaymentAttributionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrationDashboardService
{
    public function __construct(
        private readonly MigrationStatusService $statusService,
        private readonly MigrationReconciliationReportService $reconciliationReport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function homeSummary(): array
    {
        $status = $this->statusService->report();
        $phases = $this->phaseProgress();
        $repaymentStats = $this->repaymentClassificationSummary();
        $loanStats = $this->activeLoanSummary();

        return [
            'environment' => MigrationDashboardSupport::environmentLabel(),
            'is_production' => MigrationDashboardSupport::isProductionEnvironment(),
            'status' => $status,
            'phases' => $phases,
            'cards' => [
                'legacy_users' => $this->legacyCount('users'),
                'true_customers' => $status['customers']['legacy_source_rows'] ?? 0,
                'unique_target_customers' => $status['customers']['unique_target_customers'] ?? 0,
                'excluded_admin_staff' => max(0, $this->legacyCount('users') - ($status['customers']['legacy_source_rows'] ?? 0)),
                'active_loans' => $loanStats['legacy_active'],
                'promotable' => $loanStats['promotable'],
                'manual_review_loans' => $loanStats['manual_review'],
                'marketeer_customers' => $this->legacyMarketeerCustomerCount(),
                'marketeer_markets' => (int) DB::table('migration_entity_maps')
                    ->where('entity_type', MigrationEntityMapRepository::TYPE_MARKET)
                    ->count(),
            ],
            'repayments' => $repaymentStats,
            'reconciliation' => $this->reconciliationReport->summary(),
            'attention' => $this->attentionCounts(),
            'latest_runs' => $status['latest_runs'] ?? collect(),
        ];
    }

    /**
     * @return array<string, array{status: string, label: string, percent: int}>
     */
    public function phaseProgress(): array
    {
        $runs = DB::table('migration_runs')->orderByDesc('id')->get();

        return [
            'reference_data' => $this->phaseState($runs, ['m2-reference', 'm1-reference']),
            'customers' => $this->phaseState($runs, ['m2-customers']),
            'active_loans' => $this->phaseState($runs, ['m2-active-loans']),
            'repayments' => $this->phaseState($runs, ['m2-repayments', 'm1-replay']),
            'reconciliation' => $this->phaseState($runs, ['m2-reconcile', 'm1-reconcile']),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    public function repaymentClassificationSummary(): array
    {
        $fromReplay = DB::table('migration_runs')
            ->where('phase', 'like', 'm1%')
            ->orderByDesc('id')
            ->value('summary');

        $summary = $fromReplay ? json_decode($fromReplay, true) : [];
        $attribution = $summary['attribution'] ?? [];

        if ($attribution !== []) {
            return [
                'A_DIRECT' => (int) ($attribution[RepaymentAttributionService::A_DIRECT] ?? 0),
                'B_RECONSTRUCTED' => (int) ($attribution[RepaymentAttributionService::B_RECONSTRUCTED] ?? 0),
                'C_AMBIGUOUS' => (int) ($attribution[RepaymentAttributionService::C_AMBIGUOUS] ?? 0),
                'D_MANUAL' => (int) ($attribution[RepaymentAttributionService::D_MANUAL] ?? 0),
            ];
        }

        return DB::table('migration_repayments')
            ->selectRaw('attribution_class, COUNT(*) as cnt')
            ->groupBy('attribution_class')
            ->pluck('cnt', 'attribution_class')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array{legacy_active: int, promotable: int, manual_review: int, migrated: int}
     */
    public function activeLoanSummary(): array
    {
        $legacyActive = $this->legacyCount('loans', fn ($q) => $q->where('status_code', '301'));
        $migrated = (int) DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
            ->count();

        $replay = DB::table('migration_loan_replay_results')
            ->selectRaw('promotion_status, COUNT(*) as cnt')
            ->groupBy('promotion_status')
            ->pluck('cnt', 'promotion_status');

        $manual = (int) ($replay['MANUAL_REVIEW'] ?? 0) + count(ManualReviewCohorts::MANUAL_REVIEW_LOAN_IDS);
        $promotable = (int) ($replay['PROMOTABLE'] ?? max(0, $legacyActive - $manual));

        return [
            'legacy_active' => $legacyActive,
            'migrated' => $migrated,
            'promotable' => $promotable,
            'manual_review' => $manual,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function attentionCounts(): array
    {
        return [
            'customer_manual_review' => (int) DB::table('migration_customers')->where('migration_status', 'manual_review')->count(),
            'loan_manual_review' => (int) DB::table('migration_loans')->where('migration_status', 'manual_review')->count(),
            'company_manual_review' => (int) DB::table('migration_companies')->where('migration_status', 'manual_review')->count(),
            'branch_manual_review' => (int) DB::table('migration_branches')->where('migration_status', 'manual_review')->count(),
            'bank_manual_review' => (int) DB::table('migration_financial_institutions')->where('migration_status', 'manual_review')->count(),
            'wallet_manual_review' => (int) DB::table('migration_wallet_providers')->where('migration_status', 'manual_review')->count(),
            'ambiguous_repayments' => (int) DB::table('migration_repayments')->where('attribution_class', RepaymentAttributionService::C_AMBIGUOUS)->count(),
            'identity_groups' => count(CustomerIdentityResolutionRegistry::approved()),
        ];
    }

    private function legacyCount(string $table, ?callable $constraint = null): int
    {
        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $query = LegacyConnection::connection()->table($table);
            if ($constraint) {
                $constraint($query);
            }

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function legacyMarketeerCustomerCount(): int
    {
        try {
            LegacyConnection::configureFromLegacyEnvFile();

            return (int) LegacyConnection::connection()
                ->table('customers')
                ->where('is_marketize_customer', true)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  Collection<int, object>  $runs
     * @param  list<string>  $phaseNames
     * @return array{status: string, label: string, percent: int}
     */
    private function phaseState(Collection $runs, array $phaseNames): array
    {
        $phaseRuns = $runs->filter(fn ($run) => in_array($run->phase, $phaseNames, true));
        if ($phaseRuns->isEmpty()) {
            return ['status' => 'NOT_STARTED', 'label' => 'Not started', 'percent' => 0];
        }

        $latest = $phaseRuns->first();
        $summary = json_decode($latest->summary ?? '{}', true) ?: [];
        $promoted = (bool) ($summary['promote'] ?? false);

        if ($latest->status === 'failed') {
            return ['status' => 'FAILED', 'label' => 'Failed', 'percent' => 10];
        }

        if ($promoted) {
            return ['status' => 'PROMOTED', 'label' => 'Promoted', 'percent' => 100];
        }

        if ($latest->status === 'completed') {
            return ['status' => 'DRY_RUN_COMPLETE', 'label' => 'Dry run complete', 'percent' => 60];
        }

        return ['status' => 'PARTIAL', 'label' => ucfirst((string) $latest->status), 'percent' => 30];
    }
}
