<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\MigrationReconciliationReader;
use App\Models\Customer;
use App\Models\Loan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationReconciliationReportService
{
    public function __construct(
        private readonly MigrationReconciliationReader $reconciliationReader,
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
        private readonly MigrationCustomerDetailService $customerDetails,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $reconcile = $this->reconciliationReader->reconcile();

        $legacyOutstanding = 0.0;
        $targetOutstanding = 0.0;

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $maps = DB::table('migration_entity_maps')
                ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
                ->get();

            foreach ($maps as $map) {
                $legacyLoan = (array) $legacy->table('loans')->where('id', $map->legacy_identifier)->first();
                if ($legacyLoan === []) {
                    continue;
                }
                $legacyOutstanding += $this->balanceCalculator->effectiveOutstanding($legacyLoan);
                $targetLoan = Loan::find($map->target_id);
                $targetOutstanding += $targetLoan ? (float) $targetLoan->outstanding_balance : 0;
            }
        } catch (\Throwable) {
            // Legacy unavailable — use reconcile results only
        }

        return [
            'legacy_outstanding' => round($legacyOutstanding, 2),
            'target_outstanding' => round($targetOutstanding, 2),
            'variance' => round(abs($legacyOutstanding - $targetOutstanding), 2),
            'pass' => $reconcile['PASS'] ?? 0,
            'manual_review' => $reconcile['MANUAL_REVIEW'] ?? 0,
            'fail' => $reconcile['FAIL'] ?? 0,
            'pass_with_opening' => (int) DB::table('migration_loan_replay_results')
                ->where('reconciliation_status', 'PASS_WITH_MIGRATION_ADJUSTMENT')
                ->count(),
        ];
    }

    public function paginateLoans(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_loans as ml')
            ->leftJoin('migration_loan_replay_results as replay', 'replay.legacy_loan_id', '=', 'ml.legacy_loan_id')
            ->select([
                'ml.*',
                'replay.reconciliation_status',
                'replay.promotion_status',
                'replay.migration_opening_adjustment',
            ])
            ->orderByDesc('ml.id');

        if (! empty($filters['status'])) {
            $query->where('replay.reconciliation_status', $filters['status']);
        }

        if (! empty($filters['migration_status'])) {
            $query->where('ml.migration_status', $filters['migration_status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ml.legacy_loan_id', $search)
                    ->orWhere('ml.legacy_user_id', $search)
                    ->orWhere('ml.mapped_loan_id', $search);
            });
        }

        if (! empty($filters['run_id'])) {
            $query->where('ml.migration_run_id', (int) $filters['run_id']);
        }

        return $query->paginate($perPage);
    }

    public function paginateCustomers(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_customers')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('migration_status', $filters['status']);
        }

        if (! empty($filters['product'])) {
            $query->where('target_product_code', $filters['product']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('legacy_user_id', $search)
                    ->orWhere('legacy_customer_id', $search)
                    ->orWhere('mapped_customer_id', $search);
            });
        }

        if (! empty($filters['run_id'])) {
            $query->where('migration_run_id', (int) $filters['run_id']);
        }

        $paginator = $query->paginate($perPage);
        $legacyNames = $this->customerDetails->legacyNamesForRows($paginator->getCollection());
        $paginator->getCollection()->transform(function ($row) use ($legacyNames) {
            $row->legacy_name = $legacyNames[(int) $row->legacy_user_id] ?? null;
            $row->exception_label = MigrationDashboardSupport::customerExceptionLabel($row->exception ?? null);

            return $row;
        });

        return $paginator;
    }

    public function customerDetail(int $legacyUserId): ?array
    {
        $staging = DB::table('migration_customers')
            ->where('legacy_user_id', $legacyUserId)
            ->orderByDesc('id')
            ->first();

        $map = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)
            ->where('legacy_identifier', (string) $legacyUserId)
            ->orderByDesc('id')
            ->first();

        $target = $map ? Customer::find($map->target_id) : null;
        $loans = DB::table('migration_loans')->where('legacy_user_id', $legacyUserId)->get();
        $repayments = DB::table('migration_repayments')->where('legacy_user_id', $legacyUserId)->limit(50)->get();

        if ($staging === null && $map === null) {
            return null;
        }

        $identity = \App\Migration\Phases\Support\CustomerIdentityResolutionRegistry::forUser($legacyUserId);
        $enriched = $this->customerDetails->build($legacyUserId, $staging);

        return [
            'staging' => $staging,
            'map' => $map,
            'target' => $target,
            'loans' => $loans,
            'repayments' => $repayments,
            'identity' => $identity,
            'legacy' => $enriched['legacy'],
            'review' => $enriched['review'],
        ];
    }

    public function loanDetail(int $legacyLoanId): ?array
    {
        $staging = DB::table('migration_loans')->where('legacy_loan_id', $legacyLoanId)->orderByDesc('id')->first();
        $map = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_LOAN)
            ->where('legacy_identifier', (string) $legacyLoanId)
            ->first();
        $replay = DB::table('migration_loan_replay_results')->where('legacy_loan_id', $legacyLoanId)->orderByDesc('id')->first();
        $target = $map ? Loan::find($map->target_id) : null;

        return [
            'staging' => $staging,
            'map' => $map,
            'replay' => $replay,
            'target' => $target,
            'reconcile' => $this->reconciliationReader->reconcile(legacyLoanId: $legacyLoanId),
        ];
    }

    public function paginateRepayments(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_repayments')->orderByDesc('id');

        if (! empty($filters['classification'])) {
            $query->where('attribution_class', $filters['classification']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('legacy_repayment_id', $search)
                    ->orWhere('legacy_user_id', $search)
                    ->orWhere('mapped_repayment_id', $search);
            });
        }

        if (! empty($filters['run_id'])) {
            $query->where('migration_run_id', (int) $filters['run_id']);
        }

        return $query->paginate($perPage);
    }

    public function repaymentDetail(int $legacyRepaymentId): ?array
    {
        $staging = DB::table('migration_repayments')->where('legacy_repayment_id', $legacyRepaymentId)->orderByDesc('id')->first();
        $allocations = DB::table('migration_repayment_allocations')
            ->where('legacy_repayment_id', $legacyRepaymentId)
            ->orderBy('legacy_loan_id')
            ->get();

        return [
            'staging' => $staging,
            'allocations' => $allocations,
        ];
    }
}
