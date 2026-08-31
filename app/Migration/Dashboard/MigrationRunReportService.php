<?php

namespace App\Migration\Dashboard;

use App\Migration\RepaymentAttributionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationRunReportService
{
    public function __construct(
        private readonly MigrationIdentityResolutionService $identityResolutions,
    ) {}
    public function paginateRuns(int $perPage = 25): LengthAwarePaginator
    {
        return DB::table('migration_runs')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(function ($run) {
                $summary = json_decode($run->summary ?? '{}', true) ?: [];
                $counts = MigrationDashboardSupport::extractRunCounts($summary);

                return (object) [
                    'id' => $run->id,
                    'run_uuid' => $run->run_uuid,
                    'phase' => $run->phase,
                    'scope' => $run->scope,
                    'status' => $run->status,
                    'started_at' => $run->started_at,
                    'completed_at' => $run->completed_at,
                    'promote' => (bool) ($summary['promote'] ?? false),
                    'counts' => $counts,
                    'summary' => $summary,
                ];
            });
    }

    public function findRun(int $runId): ?object
    {
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run) {
            return null;
        }

        $summary = json_decode($run->summary ?? '{}', true) ?: [];

        return (object) [
            'run' => $run,
            'summary' => $summary,
            'counts' => MigrationDashboardSupport::extractRunCounts($summary),
            'attention' => $this->attentionItems($runId, $summary),
            'created_records' => DB::table('migration_created_records')
                ->where('migration_run_id', $runId)
                ->orderBy('record_type')
                ->get(),
            'entity_maps' => DB::table('migration_entity_maps')
                ->where('migration_run_id', $runId)
                ->orderBy('entity_type')
                ->limit(500)
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{all_clear: bool, items: list<array{label: string, description: string, count: int, route: string, params: array<string, mixed>}>}
     */
    public function attentionItems(int $runId, array $summary): array
    {
        $items = [];

        $customerManual = (int) DB::table('migration_customers')
            ->where('migration_run_id', $runId)
            ->where('migration_status', 'manual_review')
            ->count();
        $customerManual = max($customerManual, (int) ($summary['manual_review'] ?? 0));

        if ($customerManual > 0) {
            $items[] = [
                'label' => 'Customers needing review',
                'description' => 'Uncertain matches or possible duplicates that were not migrated.',
                'count' => $customerManual,
                'route' => 'legacy.migration-dashboard.customers.index',
                'params' => ['status' => 'manual_review', 'run_id' => $runId],
            ];
        }

        $companyManual = (int) ($summary['company_manual_review'] ?? 0);
        if ($companyManual > 0) {
            $items[] = [
                'label' => 'Company classifications to review',
                'description' => 'Legacy clients flagged as ambiguous during this customer run.',
                'count' => $companyManual,
                'route' => 'legacy.migration-dashboard.companies.index',
                'params' => ['classification' => 'AMBIGUOUS_MANUAL_REVIEW'],
            ];
        }

        $loanManual = (int) DB::table('migration_loans')
            ->where('migration_run_id', $runId)
            ->where('migration_status', 'manual_review')
            ->count();
        $loanManual = max($loanManual, (int) ($summary['manual_review_loans'] ?? 0));

        if ($loanManual > 0) {
            $items[] = [
                'label' => 'Loans needing review',
                'description' => 'Active loans blocked or flagged for manual review.',
                'count' => $loanManual,
                'route' => 'legacy.migration-dashboard.loans.index',
                'params' => ['migration_status' => 'manual_review', 'run_id' => $runId],
            ];
        }

        $repaymentManual = (int) DB::table('migration_repayments')
            ->where('migration_run_id', $runId)
            ->where(function ($q) {
                $q->where('migration_status', 'manual_review')
                    ->orWhere('attribution_class', RepaymentAttributionService::C_AMBIGUOUS);
            })
            ->count();

        if ($repaymentManual > 0) {
            $items[] = [
                'label' => 'Repayments needing review',
                'description' => 'Ambiguous or manual-attribution repayments from this run.',
                'count' => $repaymentManual,
                'route' => 'legacy.migration-dashboard.repayments.index',
                'params' => ['classification' => RepaymentAttributionService::C_AMBIGUOUS, 'run_id' => $runId],
            ];
        }

        $stagingExceptions = (int) DB::table('migration_customers')->where('migration_run_id', $runId)->whereNotNull('exception')->where('migration_status', '!=', 'created')->count()
            + (int) DB::table('migration_loans')->where('migration_run_id', $runId)->whereNotNull('exception')->count()
            + (int) DB::table('migration_repayments')->where('migration_run_id', $runId)->whereNotNull('exception')->count();

        if ($stagingExceptions > 0) {
            $items[] = [
                'label' => 'All exceptions for this run',
                'description' => 'Cross-entity view of rule violations and flagged rows.',
                'count' => $stagingExceptions,
                'route' => 'legacy.migration-dashboard.exceptions.index',
                'params' => ['run_id' => $runId],
            ];
        }

        $identity = $this->identityResolutions->summary();
        if (($identity['pending_groups'] ?? 0) > 0) {
            $items[] = [
                'label' => 'Duplicate NRC groups (identity)',
                'description' => 'Resolve before the next customer promotion if duplicates remain.',
                'count' => (int) $identity['pending_groups'],
                'route' => 'legacy.migration-dashboard.identity.index',
                'params' => [],
            ];
        }

        return [
            'all_clear' => $items === [],
            'items' => $items,
        ];
    }
}
