<?php

namespace App\Migration\ParallelRun;

use App\Migration\Dashboard\MigrationCustomerDetailService;
use App\Migration\Dashboard\MigrationDashboardSupport;
use App\Migration\LegacyConnection;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Migration\LegacyProductMapper;
use App\Migration\Phases\ManualReviewCohorts;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\Support\MigratedLoanAccrualAttributes;
use App\Migration\Phases\Support\MigratedLoanAttributes;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

class PendingLoanImportPreviewService
{
    public function __construct(
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
        private readonly LegacyProductMapper $productMapper,
        private readonly MigrationEntityMapRepository $maps,
        private readonly MigrationCustomerDetailService $customerDetails,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    public function build(int $legacyLoanId, object $inbox, array $snapshot, array $live): array
    {
        $legacyLoan = $live !== [] ? $live : $snapshot;
        $legacySource = $live !== [] ? 'live' : 'snapshot';
        $legacyUserId = (int) ($legacyLoan['user_id'] ?? $inbox->legacy_user_id ?? 0);

        $legacyCustomer = $this->loadLegacyClientContext($legacyUserId);
        $productMap = $this->productMapper->mapLoanProduct($legacyLoan, $legacyCustomer['client']);
        $loanProduct = LoanProduct::query()->where('code', $productMap['code'])->first();

        $effectiveOutstanding = $this->balanceCalculator->effectiveOutstanding($legacyLoan);
        $isAccrual = $this->balanceCalculator->isAccrualLoan($legacyLoan);
        $tenureMonths = max(1, (int) ($legacyLoan['payment_period'] ?? $legacyLoan['loan_period'] ?? 1));

        $loanStartDate = MigratedLoanAttributes::resolveLoanStartDate($legacyLoan);
        $disbursedAt = MigratedLoanAttributes::resolveDisbursedAt($legacyLoan)->toDateTimeString();
        $firstPaymentDate = MigratedLoanAttributes::resolveFirstPaymentDate($legacyLoan, $productMap['code'])->toDateString();
        $loanEndDate = ! empty($legacyLoan['due_date'])
            ? date('Y-m-d', strtotime((string) $legacyLoan['due_date']))
            : null;

        $accrualFields = $loanProduct
            ? MigratedLoanAccrualAttributes::resolve($legacyLoan, $loanProduct)
            : ['accrual_type' => 'at_beginning', 'interest_behavior' => Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT];

        $principal = (float) ($legacyLoan['obtained_amount'] ?? $legacyLoan['loan_amount'] ?? 0);
        $totalAmount = (float) ($legacyLoan['loan_amount'] ?? 0);
        $amountPaid = (float) ($legacyLoan['repaid_amount'] ?? 0);

        $revampPreview = [
            'loan_number' => 'LEG-'.$legacyLoanId,
            'customer_id' => null,
            'customer_name' => null,
            'loan_product_code' => $productMap['code'],
            'loan_product_name' => $loanProduct?->name,
            'principal_amount' => $principal,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'outstanding_balance' => $effectiveOutstanding,
            'tenure_months' => $tenureMonths,
            'loan_start_date' => $loanStartDate,
            'loan_end_date' => $loanEndDate,
            'first_payment_date' => $firstPaymentDate,
            'disbursed_at' => $disbursedAt,
            'status' => 'active',
            'disbursement_status' => 'completed',
            'accrual_type' => $accrualFields['accrual_type'],
            'interest_behavior' => $accrualFields['interest_behavior'],
            'repayment_structure' => MigratedLoanAttributes::repaymentStructureForProduct($productMap['code']),
            'interest_accrued_on_import' => $accrualFields['accrual_type'] === 'daily' ? '0.00 (caught up after import)' : null,
        ];

        $customerMap = $this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $legacyUserId);
        if ($customerMap) {
            $revampPreview['customer_id'] = (int) $customerMap->target_id;
            $customer = Customer::query()->find($customerMap->target_id);
            $revampPreview['customer_name'] = $customer?->full_name;
        }

        $existingLoanMap = $this->maps->find(MigrationEntityMapRepository::TYPE_LOAN, (string) $legacyLoanId);
        $reconciliation = (string) (DB::table('migration_loan_replay_results')
            ->where('legacy_loan_id', $legacyLoanId)
            ->value('reconciliation_status') ?? 'PASS_WITH_MIGRATION_ADJUSTMENT');
        $cohort = ManualReviewCohorts::loanCohort($legacyLoanId, $reconciliation);

        $legacyPerson = $this->customerDetails->build($legacyUserId, null)['legacy'];

        $readiness = $this->buildReadiness(
            $legacyLoanId,
            $legacyUserId,
            $legacyLoan,
            $loanProduct,
            $customerMap,
            $existingLoanMap,
            $cohort,
        );

        $legacyDisplay = $this->buildLegacyDisplay(
            $legacyLoan,
            $legacySource,
            $legacyPerson,
            $productMap,
            $effectiveOutstanding,
            $isAccrual,
            $tenureMonths,
        );

        return [
            'legacy_loan_id' => $legacyLoanId,
            'legacy_source' => $legacySource,
            'legacy' => $legacyDisplay,
            'revamp' => $revampPreview,
            'readiness' => $readiness,
            'comparisons' => $this->buildComparisons($legacyDisplay, $revampPreview, $isAccrual, $accrualFields),
            'post_import' => [
                'repayments_synced' => true,
                'accrual_catch_up' => $accrualFields['accrual_type'] === 'daily',
                'schedule_refresh' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $legacyClient
     * @return array{client: array<string, mixed>|null}
     */
    private function loadLegacyClientContext(int $legacyUserId): array
    {
        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $legacyCustomer = (array) $legacy->table('customers')->where('user_id', $legacyUserId)->first();
            $legacyClient = $legacyCustomer && ($legacyCustomer['client_id'] ?? null)
                ? (array) $legacy->table('clients')->where('id', $legacyCustomer['client_id'])->first()
                : null;

            return ['client' => $legacyClient];
        } catch (\Throwable) {
            return ['client' => null];
        }
    }

    /**
     * @param  array<string, mixed>  $legacyLoan
     * @param  array{code: string, category: string, reason: string}  $productMap
     * @param  array<string, mixed>  $legacyPerson
     * @return array<string, mixed>
     */
    private function buildLegacyDisplay(
        array $legacyLoan,
        string $source,
        array $legacyPerson,
        array $productMap,
        float $effectiveOutstanding,
        bool $isAccrual,
        int $tenureMonths,
    ): array {
        return [
            'available' => $legacyLoan !== [],
            'source' => $source,
            'loan_id' => (int) ($legacyLoan['id'] ?? 0),
            'status_code' => (string) ($legacyLoan['status_code'] ?? ''),
            'customer' => [
                'legacy_user_id' => (int) ($legacyLoan['user_id'] ?? 0),
                'full_name' => $legacyPerson['full_name'] ?? null,
                'national_id' => $legacyPerson['national_id'] ?? null,
                'phone' => $legacyPerson['phone'] ?? null,
                'product_type' => $legacyPerson['product_type'] ?? null,
            ],
            'product_code' => $productMap['code'],
            'product_reason' => $productMap['reason'],
            'is_accrual_loan' => $isAccrual,
            'financials' => [
                'principal_obtained' => (float) ($legacyLoan['obtained_amount'] ?? 0),
                'total_repayment_amount' => (float) ($legacyLoan['loan_amount'] ?? 0),
                'amount_paid' => (float) ($legacyLoan['repaid_amount'] ?? 0),
                'effective_outstanding' => $effectiveOutstanding,
                'fixed_balance' => $this->balanceCalculator->fixedProductOutstanding($legacyLoan),
                'current_loan_amount' => isset($legacyLoan['current_loan_amount']) ? (float) $legacyLoan['current_loan_amount'] : null,
                'principle_amount' => isset($legacyLoan['principle_amount']) ? (float) $legacyLoan['principle_amount'] : null,
                'accrued_interest' => isset($legacyLoan['accrued_interest']) ? (float) $legacyLoan['accrued_interest'] : null,
                'accrued_days' => isset($legacyLoan['accrued_days']) ? (int) $legacyLoan['accrued_days'] : null,
                'daily_added_interest' => isset($legacyLoan['daily_added_interest']) ? (float) $legacyLoan['daily_added_interest'] : null,
                'initial_installment' => isset($legacyLoan['initial_month_installment']) ? (float) $legacyLoan['initial_month_installment'] : null,
                'current_installment' => isset($legacyLoan['monthly_payment']) ? (float) $legacyLoan['monthly_payment'] : null,
            ],
            'dates' => [
                'created_at' => $legacyLoan['created_at'] ?? null,
                'due_date' => $legacyLoan['due_date'] ?? null,
                'first_repayment_date' => $legacyLoan['first_repayment_date'] ?? null,
                'settled_date' => $legacyLoan['settled_date'] ?? null,
            ],
            'tenure_months' => $tenureMonths,
            'flags' => [
                'salary_based' => (bool) ($legacyLoan['salary_based'] ?? false),
                'gvnt_loan' => (bool) ($legacyLoan['gvnt_loan'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $legacyLoan
     * @return array<string, mixed>
     */
    private function buildReadiness(
        int $legacyLoanId,
        int $legacyUserId,
        array $legacyLoan,
        ?LoanProduct $loanProduct,
        ?object $customerMap,
        ?object $existingLoanMap,
        string $cohort,
    ): array {
        $blockers = [];
        $warnings = [];

        if (($legacyLoan['status_code'] ?? '') !== '301') {
            $blockers[] = 'Legacy loan is not active (status 301).';
        }

        if (! $customerMap) {
            $blockers[] = "Customer not mapped for legacy user {$legacyUserId}. Migrate or map the customer first.";
        }

        if (! $loanProduct) {
            $blockers[] = 'Target loan product is missing in revamp.';
        }

        if ($existingLoanMap) {
            $warnings[] = 'A revamp loan map already exists — import will match the existing loan instead of creating a new one.';
        }

        if (! ManualReviewCohorts::isPromotable($cohort)) {
            $blockers[] = "Loan cohort {$cohort} requires manual review and cannot be auto-imported.";
        }

        if ($this->balanceCalculator->isAccrualLoan($legacyLoan)) {
            $warnings[] = 'MOU/GRZ accrual loan: daily interest will be caught up on import; legacy accrued interest is not copied as a lump sum.';
        }

        return [
            'can_import' => $blockers === [],
            'cohort' => $cohort,
            'reconciliation_status' => DB::table('migration_loan_replay_results')
                ->where('legacy_loan_id', $legacyLoanId)
                ->value('reconciliation_status'),
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<string, mixed>  $revamp
     * @param  array{accrual_type: string, interest_behavior: string}  $accrualFields
     * @return list<array<string, mixed>>
     */
    private function buildComparisons(array $legacy, array $revamp, bool $isAccrual, array $accrualFields): array
    {
        $rows = [
            $this->compareRow('Customer', $this->formatLegacyCustomer($legacy), $this->formatRevampCustomer($revamp)),
            $this->compareRow('Product', $legacy['product_code'] ?? '—', $revamp['loan_product_code'] ?? '—'),
            $this->compareMoney('Principal (disbursed)', $legacy['financials']['principal_obtained'] ?? 0, $revamp['principal_amount'] ?? 0),
            $this->compareMoney('Total repayment (booked)', $legacy['financials']['total_repayment_amount'] ?? 0, $revamp['total_amount'] ?? 0),
            $this->compareMoney('Amount paid', $legacy['financials']['amount_paid'] ?? 0, $revamp['amount_paid'] ?? 0),
            $this->compareMoney(
                'Outstanding balance',
                $legacy['financials']['effective_outstanding'] ?? 0,
                $revamp['outstanding_balance'] ?? 0,
                critical: true,
            ),
            $this->compareRow('Tenure (months)', (string) ($legacy['tenure_months'] ?? '—'), (string) ($revamp['tenure_months'] ?? '—')),
            $this->compareDate('Loan start', $legacy['dates']['created_at'] ?? null, $revamp['loan_start_date'] ?? null),
            $this->compareDate('Disbursed at', $legacy['dates']['created_at'] ?? null, $revamp['disbursed_at'] ?? null, datetime: true),
            $this->compareDate('Due / end date', $legacy['dates']['due_date'] ?? null, $revamp['loan_end_date'] ?? null),
            $this->compareDate('First payment', $legacy['dates']['first_repayment_date'] ?? null, $revamp['first_payment_date'] ?? null),
            $this->compareRow('Loan number', '#'.($legacy['loan_id'] ?? '—'), $revamp['loan_number'] ?? '—', informational: true),
            $this->compareRow(
                'Accrual model',
                $isAccrual ? 'Daily accrual (MOU/GRZ)' : 'Upfront / fixed',
                $accrualFields['accrual_type'] === 'daily' ? 'Daily accrual' : 'Upfront flat',
            ),
            $this->compareRow('Repayment structure', '—', ucfirst($revamp['repayment_structure'] ?? '—'), informational: true),
        ];

        if ($isAccrual) {
            $rows[] = $this->compareRow(
                'Legacy accrued interest',
                MigrationDashboardSupport::formatZmw($legacy['financials']['accrued_interest'] ?? 0),
                'Earned via accrual cron after import',
                informational: true,
            );
            $rows[] = $this->compareRow(
                'Legacy accrued days',
                (string) ($legacy['financials']['accrued_days'] ?? '—'),
                'Recalculated on import catch-up',
                informational: true,
            );
            $rows[] = $this->compareRow(
                'Legacy daily interest',
                MigrationDashboardSupport::formatZmw($legacy['financials']['daily_added_interest'] ?? 0),
                'Derived from rate row / accrual settings',
                informational: true,
            );
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function compareRow(
        string $label,
        ?string $legacyValue,
        ?string $revampValue,
        bool $informational = false,
        bool $critical = false,
    ): array {
        $legacyValue = $legacyValue ?? '—';
        $revampValue = $revampValue ?? '—';

        $status = 'match';
        if ($informational) {
            $status = 'info';
        } elseif ($legacyValue !== $revampValue && $legacyValue !== '—' && $revampValue !== '—') {
            $status = $critical ? 'critical' : 'diff';
        }

        return compact('label', 'legacyValue', 'revampValue', 'status');
    }

    /**
     * @return array<string, mixed>
     */
    private function compareMoney(string $label, float $legacyAmount, float $revampAmount, bool $critical = false): array
    {
        $match = abs($legacyAmount - $revampAmount) < 0.01;

        return [
            'label' => $label,
            'legacyValue' => MigrationDashboardSupport::formatZmw($legacyAmount),
            'revampValue' => MigrationDashboardSupport::formatZmw($revampAmount),
            'status' => $match ? 'match' : ($critical ? 'critical' : 'diff'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compareDate(
        string $label,
        mixed $legacyValue,
        mixed $revampValue,
        bool $datetime = false,
        bool $critical = false,
    ): array {
        $legacyFormatted = $this->formatDateValue($legacyValue, $datetime);
        $revampFormatted = $this->formatDateValue($revampValue, $datetime);

        $legacyKey = $this->normalizeDateKey($legacyValue, $datetime);
        $revampKey = $this->normalizeDateKey($revampValue, $datetime);

        $status = ($legacyKey !== null && $revampKey !== null && $legacyKey === $revampKey) ? 'match' : 'diff';
        if ($legacyKey === null || $revampKey === null) {
            $status = ($legacyFormatted === $revampFormatted) ? 'match' : 'diff';
        }
        if ($critical && $status === 'diff') {
            $status = 'critical';
        }

        return [
            'label' => $label,
            'legacyValue' => $legacyFormatted,
            'revampValue' => $revampFormatted,
            'status' => $status,
        ];
    }

    private function formatDateValue(mixed $value, bool $datetime): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return (string) $value;
        }

        return $datetime ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d', $timestamp);
    }

    private function normalizeDateKey(mixed $value, bool $datetime): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return $datetime ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d', $timestamp);
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function formatLegacyCustomer(array $legacy): string
    {
        $userId = $legacy['customer']['legacy_user_id'] ?? 0;
        $name = $legacy['customer']['full_name'] ?? null;

        return trim(($name ? $name.' ' : '')."(user #{$userId})");
    }

    /**
     * @param  array<string, mixed>  $revamp
     */
    private function formatRevampCustomer(array $revamp): string
    {
        if (! $revamp['customer_id']) {
            return 'Not mapped';
        }

        $name = $revamp['customer_name'] ?? 'Customer';

        return "{$name} (#{$revamp['customer_id']})";
    }
}
