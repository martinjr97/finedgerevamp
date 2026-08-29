<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\Phases\Support\CustomerIdentityResolutionRegistry;
use Illuminate\Support\Facades\DB;

class MigrationStatusService
{
    public function report(): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $productCodes = ['MOU-001', 'GOV-001', 'CHAR-001', 'MARK-001'];
        $productsMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_PRODUCT)
            ->whereIn('legacy_identifier', $productCodes)
            ->count();

        $companiesMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_COMPANY)
            ->count();

        $groupsMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP)
            ->count();

        $marketsMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_MARKET)
            ->count();

        $banksMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_FINANCIAL_INSTITUTION)
            ->count();

        $providersMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_WALLET_PROVIDER)
            ->count();

        $branchesMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_BRANCH)
            ->count();

        $relationshipManagersMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER)
            ->count();

        $branchManualReview = (int) DB::table('migration_branches')->where('migration_status', 'manual_review')->count();
        $bankManualReview = (int) DB::table('migration_financial_institutions')->where('migration_status', 'manual_review')->count();
        $walletManualReview = (int) DB::table('migration_wallet_providers')->where('migration_status', 'manual_review')->count();

        $legacyCustomers = (int) $legacy->table('customers')->count();
        $customerMaps = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->count();
        $distinctCustomerTargets = (int) DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->distinct()
            ->count('target_id');

        $legacyActiveLoans = (int) $legacy->table('loans')->where('status_code', '301')->count();
        $loansMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
            ->count();

        $repaymentsMapped = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_REPAYMENT)
            ->count();

        $latestReplay = DB::table('migration_runs')->where('phase', 'like', 'm1%')->orderByDesc('id')->first();
        $replaySummary = $latestReplay ? json_decode($latestReplay->summary ?? '{}', true) : [];

        $reconcileRun = DB::table('migration_runs')->where('phase', 'm2-reconcile')->orderByDesc('id')->first();
        $reconcileSummary = $reconcileRun ? json_decode($reconcileRun->summary ?? '{}', true) : [];

        $targetOutstanding = (float) DB::table('loans')->where('status', 'active')->sum('outstanding_balance');

        return [
            'reference_data' => [
                'products' => "{$productsMapped}/4 mapped",
                'companies' => "{$companiesMapped} mapped",
                'groups' => "{$groupsMapped} mapped",
                'markets' => "{$marketsMapped} mapped",
                'banks' => "{$banksMapped} matched",
                'wallet_providers' => "{$providersMapped} matched",
                'branches' => "{$branchesMapped} mapped",
                'relationship_managers' => "{$relationshipManagersMapped} mapped",
                'reference_manual_review' => [
                    'branches' => $branchManualReview,
                    'banks' => $bankManualReview,
                    'wallet_providers' => $walletManualReview,
                ],
            ],
            'customers' => [
                'legacy_source_rows' => $legacyCustomers,
                'unique_target_customers' => $legacyCustomers - CustomerIdentityResolutionRegistry::aliasLegacyUserCount(),
                'mapped_legacy_identities' => $customerMaps,
                'distinct_target_records' => $distinctCustomerTargets,
                'identity_alias_groups' => count(CustomerIdentityResolutionRegistry::approved()),
            ],
            'active_loans' => [
                'legacy_active' => $legacyActiveLoans,
                'migrated' => $loansMapped,
                'manual_excluded' => count(ManualReviewCohorts::MANUAL_REVIEW_LOAN_IDS),
            ],
            'repayments' => [
                'mapped' => $repaymentsMapped,
                'attribution' => $replaySummary['attribution'] ?? null,
            ],
            'financial' => [
                'target_outstanding' => round($targetOutstanding, 2),
                'reconciliation' => [
                    'PASS' => $reconcileSummary['PASS'] ?? null,
                    'FAIL' => $reconcileSummary['FAIL'] ?? null,
                    'MANUAL_REVIEW' => $reconcileSummary['MANUAL_REVIEW'] ?? null,
                ],
            ],
            'reconciliation' => $replaySummary['loan_status'] ?? null,
            'latest_runs' => DB::table('migration_runs')->orderByDesc('id')->limit(8)->get(['id', 'run_uuid', 'phase', 'status', 'completed_at']),
        ];
    }
}
