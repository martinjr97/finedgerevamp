<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Migration\LegacyProductMapper;
use App\Migration\Phases\Support\MigratedLoanAttributes;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

class ActiveLoanMigrator
{
    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly MigrationEntityMapRepository $maps,
        private readonly MigrationDependencyGate $gate,
        private readonly LegacyProductMapper $productMapper,
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $promote = false,
        ?int $legacyLoanId = null,
        ?int $legacyCustomerUserId = null,
        ?int $limit = null,
        ?string $runUuid = null,
    ): array {
        if ($promote) {
            app(MigrationPromotionGate::class)->assertActiveLoansPromote();
        }

        $this->gate->requireProductsMapped();

        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $run = $this->runManager->start('m2-active-loans', 'status-301', $runUuid);
        $runId = $run['id'];

        $query = $legacy->table('loans')->where('status_code', '301')->orderBy('id');
        if ($legacyLoanId) {
            $query->where('id', $legacyLoanId);
        }
        if ($legacyCustomerUserId) {
            $query->where('user_id', $legacyCustomerUserId);
        }
        if ($limit) {
            $query->limit($limit);
        }

        $stats = [
            'run_uuid' => $run['run_uuid'],
            'migration_run_id' => $runId,
            'promote' => $promote,
            'legacy_active_loans' => 0,
            'would_create' => 0,
            'created' => 0,
            'matched_existing' => 0,
            'blocked_missing_customer' => 0,
            'manual_review' => 0,
            'promotable' => 0,
            'blocked' => 0,
        ];

        $loans = $query->get();

        $replayStatuses = DB::table('migration_loan_replay_results')
            ->whereIn('legacy_loan_id', $loans->pluck('id')->all())
            ->pluck('reconciliation_status', 'legacy_loan_id');

        foreach ($loans as $loanRow) {
            $loan = (array) $loanRow;
            $legacyLoanId = (int) $loan['id'];
            $userId = (int) $loan['user_id'];
            $stats['legacy_active_loans']++;

            $reconciliation = $replayStatuses[$legacyLoanId] ?? 'PASS_WITH_MIGRATION_ADJUSTMENT';
            $cohort = ManualReviewCohorts::loanCohort($legacyLoanId, $reconciliation);

            if (! ManualReviewCohorts::isPromotable($cohort)) {
                $stats['manual_review']++;
                $this->stageLoan($runId, $loan, null, $cohort, 'manual_review');
                if ($cohort === 'COHORT_D_BLOCKED') {
                    $stats['blocked']++;
                }

                continue;
            }

            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_LOAN, (string) $legacyLoanId);
            if ($existingMap) {
                $stats['matched_existing']++;
                $this->stageLoan($runId, $loan, (int) $existingMap->target_id, $cohort, 'matched_existing');

                continue;
            }

            try {
                $customerId = $this->gate->requireCustomerMapped($userId);
            } catch (\RuntimeException) {
                $stats['blocked_missing_customer']++;
                $this->stageLoan($runId, $loan, null, $cohort, 'blocked_missing_customer');

                continue;
            }

            $legacyCustomer = (array) $legacy->table('customers')->where('user_id', $userId)->first();
            $legacyClient = $legacyCustomer && ($legacyCustomer['client_id'] ?? null)
                ? (array) $legacy->table('clients')->where('id', $legacyCustomer['client_id'])->first()
                : null;

            $productMap = $this->productMapper->mapLoanProduct($loan, $legacyClient);
            $loanProduct = LoanProduct::query()->where('code', $productMap['code'])->first();
            if (! $loanProduct) {
                $stats['blocked']++;
                $this->stageLoan($runId, $loan, null, $cohort, 'blocked_missing_product');

                continue;
            }

            $effective = $this->balanceCalculator->effectiveOutstanding($loan);

            if (! $promote) {
                $stats['would_create']++;
                $stats['promotable']++;
                $this->stageLoan($runId, $loan, null, $cohort, 'would_create', $productMap['code'], $effective);

                continue;
            }

            $loanNumber = 'LEG-'.$legacyLoanId;
            $existingLoan = Loan::query()
                ->where('loan_number', $loanNumber)
                ->orWhere('metadata->legacy_loan_id', $legacyLoanId)
                ->first();

            if ($existingLoan) {
                $stats['matched_existing']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_LOAN,
                    (string) $legacyLoanId,
                    Loan::class,
                    $existingLoan->id,
                    'matched_existing',
                    'HIGH',
                    null,
                    $runId
                );
                $this->stageLoan($runId, $loan, $existingLoan->id, $cohort, 'matched_existing', $productMap['code'], $effective);

                continue;
            }

            $loanStartDate = MigratedLoanAttributes::resolveLoanStartDate($loan);
            $firstPaymentDate = MigratedLoanAttributes::resolveFirstPaymentDate($loan, $productMap['code']);
            $disbursedAt = MigratedLoanAttributes::resolveDisbursedAt($loan);

            $target = Loan::create([
                'customer_id' => $customerId,
                'loan_product_id' => $loanProduct->id,
                'loan_number' => $loanNumber,
                'principal_amount' => (float) ($loan['obtained_amount'] ?? $loan['loan_amount'] ?? 0),
                'total_amount' => (float) ($loan['loan_amount'] ?? 0),
                'amount_paid' => (float) ($loan['repaid_amount'] ?? 0),
                'outstanding_balance' => $effective,
                'tenure_months' => max(1, (int) ($loan['payment_period'] ?? 1)),
                'loan_start_date' => $loanStartDate,
                'loan_end_date' => ! empty($loan['due_date']) ? date('Y-m-d', strtotime((string) $loan['due_date'])) : null,
                'first_payment_date' => $firstPaymentDate->toDateString(),
                'status' => 'active',
                'disbursement_status' => 'completed',
                'disbursed_at' => $disbursedAt,
                'metadata' => [
                    'legacy_loan_id' => $legacyLoanId,
                    'legacy_user_id' => $userId,
                    'legacy_effective_outstanding' => $effective,
                    'migration_cohort' => $cohort,
                    'repayment_structure' => MigratedLoanAttributes::repaymentStructureForProduct($productMap['code']),
                ],
            ]);

            $target->createPaymentSchedule();

            $stats['created']++;
            $stats['promotable']++;
            $this->maps->store(MigrationEntityMapRepository::TYPE_LOAN, (string) $legacyLoanId, Loan::class, $target->id, 'created', 'HIGH', null, $runId);
            $this->maps->trackCreated($runId, Loan::class, $target->id);
            $this->stageLoan($runId, $loan, $target->id, $cohort, 'created', $productMap['code'], $effective);
        }

        $this->runManager->complete($runId, $stats);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    private function stageLoan(
        int $runId,
        array $loan,
        ?int $mappedId,
        string $cohort,
        string $status,
        ?string $productCode = null,
        ?float $effective = null,
    ): void {
        DB::table('migration_loans')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_loan_id' => $loan['id']],
            [
                'legacy_user_id' => $loan['user_id'],
                'mapped_loan_id' => $mappedId,
                'target_product_code' => $productCode,
                'legacy_effective_outstanding' => $effective ?? $this->balanceCalculator->effectiveOutstanding($loan),
                'migration_status' => $status,
                'exception' => $cohort,
                'raw_context' => json_encode(['cohort' => $cohort]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
