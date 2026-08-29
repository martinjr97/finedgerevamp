<?php

namespace App\Migration;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPaymentDetail;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Models\WalletProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class M1PilotImporter
{
    public const PILOT_LOAN_IDS = [
        2584, 2631, 2882, 187, 230, 232, 7803, 14227, 14589, 16171,
        450, 476, 477, 18064, 18237, 1, 2, 1678, 17753, 18369,
    ];

    /** Active loans only — M1 import scope */
    public const PILOT_ACTIVE_LOAN_IDS = [
        2584, 2631, 2882, 7803, 14227, 14589, 16171, 18064, 18237, 17753, 18369,
    ];

    public function __construct(
        private readonly LegacyLoanBalanceCalculator $balanceCalculator,
        private readonly LegacyProductMapper $productMapper,
        private readonly RepaymentAttributionService $attributionService,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function import(int $migrationRunId, bool $promote = false): array
    {
        $legacy = LegacyConnection::connection();
        $loanIds = self::PILOT_LOAN_IDS;
        $activeImportIds = self::PILOT_ACTIVE_LOAN_IDS;

        $legacyLoans = $legacy->table('loans')->whereIn('id', $loanIds)->get()->map(fn ($r) => (array) $r);
        $userIds = $legacyLoans->pluck('user_id')->unique()->values()->all();

        $this->ensureMarketeerProduct();

        $companyMap = [];
        $customerMap = [];
        $importedLoans = 0;
        $importedRepayments = 0;
        $skippedSettledLoans = 0;

        foreach ($userIds as $userId) {
            $legacyUser = (array) $legacy->table('users')->where('id', $userId)->first();
            $legacyCustomer = (array) $legacy->table('customers')->where('user_id', $userId)->first();
            $legacyClient = $legacyCustomer
                ? (array) $legacy->table('clients')->where('id', $legacyCustomer['client_id'])->first()
                : null;

            $companyId = null;
            if ($legacyClient) {
                $companyId = $this->upsertCompany($migrationRunId, $legacyClient, $companyMap, $promote);
            }

            $customerId = $this->upsertCustomer($migrationRunId, $legacyUser, $legacyCustomer, $legacyClient, $companyId, $promote);
            $customerMap[$userId] = $customerId;

            $this->upsertPaymentDetails($migrationRunId, $legacyUser, $legacyCustomer, $customerId, $promote);
        }

        foreach ($legacyLoans as $loan) {
            $isActiveImport = in_array((int) $loan['id'], $activeImportIds, true);
            if (! $isActiveImport) {
                $skippedSettledLoans++;
                $this->stageLoan($migrationRunId, $loan, null, null, 'skipped_settled_support_only');

                continue;
            }

            $customerId = $customerMap[$loan['user_id']] ?? null;
            $legacyCustomer = (array) $legacy->table('customers')->where('user_id', $loan['user_id'])->first();
            $legacyClient = $legacyCustomer
                ? (array) $legacy->table('clients')->where('id', $legacyCustomer['client_id'])->first()
                : null;

            $productMap = $this->productMapper->mapLoanProduct($loan, $legacyClient);
            $loanProduct = LoanProduct::where('code', $productMap['code'])->first();
            $effective = $this->balanceCalculator->effectiveOutstanding($loan);

            $targetLoanId = null;
            if ($promote && $customerId && $loanProduct) {
                $targetLoanId = $this->promoteLoan($loan, $customerId, $loanProduct, $effective);
                $importedLoans++;
            }

            $this->stageLoan($migrationRunId, $loan, $targetLoanId, $effective, $promote ? 'imported' : 'staged', $productMap['code']);
        }

        $processedRepayments = [];
        foreach ($userIds as $userId) {
            $legacyCustomer = (array) $legacy->table('customers')->where('user_id', $userId)->first();
            $legacyClient = $legacyCustomer
                ? (array) $legacy->table('clients')->where('id', $legacyCustomer['client_id'])->first()
                : null;
            $customerId = $customerMap[$userId] ?? null;

            $repayments = $legacy->table('repayments')
                ->where('user_id', $userId)
                ->where('status_code', 215)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($r) => (array) $r);

            foreach ($repayments as $repayment) {
                if (isset($processedRepayments[$repayment['id']])) {
                    continue;
                }
                $processedRepayments[$repayment['id']] = true;

                $activeAtPayment = $legacy->table('loans')
                    ->where('user_id', $userId)
                    ->where('status_code', '301')
                    ->where('created_at', '<=', $repayment['created_at'])
                    ->get()->map(fn ($r) => (array) $r)->all();

                $classification = $this->attributionService->classify($repayment, $activeAtPayment, $legacyClient);
                $targetRepaymentId = null;

                if ($promote && $customerId && $classification['class'] === RepaymentAttributionService::A_DIRECT) {
                    $pilotLoanId = $this->resolvePilotLoanForRepayment($legacy, $userId, $repayment, $classification);
                    $mappedLoanId = $pilotLoanId
                        ? DB::table('migration_loans')
                            ->where('migration_run_id', $migrationRunId)
                            ->where('legacy_loan_id', $pilotLoanId)
                            ->value('mapped_loan_id')
                        : null;

                    if ($mappedLoanId) {
                        $targetRepaymentId = $this->promoteRepayment($repayment, $customerId, (int) $mappedLoanId, $classification, $pilotLoanId);
                        if ($targetRepaymentId) {
                            $importedRepayments++;
                        }
                    }
                }

                DB::table('migration_repayments')->updateOrInsert(
                    ['migration_run_id' => $migrationRunId, 'legacy_repayment_id' => $repayment['id']],
                    [
                        'legacy_user_id' => $repayment['user_id'],
                        'mapped_repayment_id' => $targetRepaymentId,
                        'attribution_class' => $classification['class'],
                        'repayment_amount' => (float) $repayment['repayment_amount'],
                        'migration_status' => $classification['class'] === RepaymentAttributionService::C_AMBIGUOUS ? 'manual_review' : ($promote ? 'imported' : 'staged'),
                        'confidence' => $classification['class'] === RepaymentAttributionService::A_DIRECT ? 'HIGH' : 'MEDIUM',
                        'exception' => $classification['class'] === RepaymentAttributionService::C_AMBIGUOUS ? $classification['reason'] : null,
                        'allocations' => json_encode($classification['allocations']),
                        'raw_context' => json_encode(['reason' => $classification['reason']]),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        return [
            'migration_run_id' => $migrationRunId,
            'promote' => $promote,
            'customers' => count($customerMap),
            'active_loans_imported' => $importedLoans,
            'settled_loans_skipped' => $skippedSettledLoans,
            'repayments_imported' => $importedRepayments,
        ];
    }

    private function ensureMarketeerProduct(): void
    {
        LoanProduct::updateOrCreate(
            ['code' => 'MARK-001'],
            [
                'company_id' => Company::query()->value('id') ?? 1,
                'name' => 'Marketeer Loan',
                'category' => 'marketeer',
                'description' => 'Weekly market trader loans migrated from legacy marketize.',
                'tenure_months' => 1,
                'max_amount' => 500_000,
                'requires_collateral' => false,
                'requires_reference' => false,
                'is_active' => true,
                'rules' => [],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $legacyClient
     * @param  array<int, int>  $companyMap
     */
    private function upsertCompany(int $runId, array $legacyClient, array &$companyMap, bool $promote): ?int
    {
        $legacyId = (int) $legacyClient['id'];
        if (isset($companyMap[$legacyId])) {
            return $companyMap[$legacyId];
        }

        $mappedId = null;
        $matchStrategy = 'normalized_name';

        if ($promote) {
            $company = Company::updateOrCreate(
                ['code' => 'LEG-'.$legacyId],
                [
                    'name' => $legacyClient['company_name'] ?? ('Legacy Client '.$legacyId),
                    'slug' => Str::slug($legacyClient['company_name'] ?? 'legacy-'.$legacyId),
                    'type' => 'partner',
                    'registration_number' => $legacyClient['reg_number'] ?? null,
                    'contact_email' => $legacyClient['email'] ?? null,
                    'contact_phone' => $legacyClient['primary_number'] ?? null,
                    'address_line1' => $legacyClient['address'] ?? null,
                    'city' => $legacyClient['town'] ?? null,
                    'state' => $legacyClient['province'] ?? null,
                    'country' => $legacyClient['country'] ?? 'Zambia',
                    'pay_day' => is_numeric($legacyClient['pay_date'] ?? null) ? (int) $legacyClient['pay_date'] : null,
                    'monthly_cut_off_day' => is_numeric($legacyClient['cut_off_date'] ?? null) ? (int) $legacyClient['cut_off_date'] : null,
                    'maximum_loan_tenure_months' => is_numeric($legacyClient['loan_tenure'] ?? null) ? (int) $legacyClient['loan_tenure'] : null,
                    'status' => 'active',
                    'approval_status' => 'approved',
                    'settings' => [
                        'legacy_client_id' => $legacyId,
                        'legacy_product_type' => $legacyClient['product_type'] ?? null,
                    ],
                ]
            );
            $mappedId = $company->id;
        }

        DB::table('migration_companies')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_client_id' => $legacyId],
            [
                'mapped_company_id' => $mappedId,
                'match_strategy' => $matchStrategy,
                'migration_status' => $promote ? 'imported' : 'staged',
                'confidence' => 'HIGH',
                'raw_context' => json_encode(['company_name' => $legacyClient['company_name'] ?? null]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $companyMap[$legacyId] = $mappedId;

        return $mappedId;
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     * @param  array<string, mixed>|null  $legacyCustomer
     * @param  array<string, mixed>|null  $legacyClient
     */
    private function upsertCustomer(int $runId, array $legacyUser, ?array $legacyCustomer, ?array $legacyClient, ?int $companyId, bool $promote): ?int
    {
        $userId = (int) $legacyUser['id'];
        $productMap = $legacyClient ? $this->productMapper->mapLoanProduct(['salary_based' => 0, 'gvnt_loan' => 0], $legacyClient) : ['code' => 'CHAR-001'];
        $loanProduct = LoanProduct::where('code', $productMap['code'])->first();

        $mappedId = null;
        if ($promote && $legacyCustomer) {
            $customer = Customer::updateOrCreate(
                ['email' => $legacyUser['email'] ?? ('legacy-user-'.$userId.'@migration.local')],
                [
                    'company_id' => in_array($productMap['code'], ['CHAR-001', 'MARK-001'], true) ? null : $companyId,
                    'loan_product_id' => $loanProduct?->id,
                    'first_name' => $legacyUser['fname'] ?? 'Legacy',
                    'last_name' => $legacyUser['lname'] ?? 'Customer',
                    'phone' => $this->phoneNormalizer->normalize($legacyUser['phone_number'] ?? null) ?? $legacyUser['phone_number'],
                    'national_id' => $legacyCustomer['nrc'] ?? $legacyUser['nrc'] ?? null,
                    'employee_number' => $legacyUser['emp_number'] ?? null,
                    'gross_salary' => is_numeric($legacyCustomer['gross_salary'] ?? null) ? $legacyCustomer['gross_salary'] : null,
                    'net_salary' => is_numeric($legacyCustomer['net_pay'] ?? null) ? $legacyCustomer['net_pay'] : null,
                    'status' => 'active',
                    'kyc_status' => 'verified',
                    'password' => bcrypt('MigrationPilot!'.Str::random(8)),
                    'metadata' => [
                        'legacy_user_id' => $userId,
                        'legacy_customer_id' => $legacyCustomer['id'] ?? null,
                        'legacy_client_id' => $legacyCustomer['client_id'] ?? null,
                    ],
                ]
            );
            $mappedId = $customer->id;
        }

        DB::table('migration_customers')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_user_id' => $userId],
            [
                'legacy_customer_id' => $legacyCustomer['id'] ?? null,
                'legacy_client_id' => $legacyCustomer['client_id'] ?? null,
                'mapped_customer_id' => $mappedId,
                'target_product_code' => $productMap['code'],
                'migration_status' => $promote ? 'imported' : 'staged',
                'confidence' => 'HIGH',
                'completeness' => json_encode([
                    'biodata' => $legacyCustomer ? 'PASS' : 'INVALID',
                    'company' => $companyId || in_array($productMap['code'], ['CHAR-001', 'MARK-001'], true) ? 'PASS' : 'UNMAPPED',
                ]),
                'raw_context' => json_encode(['fname' => $legacyUser['fname'] ?? null, 'lname' => $legacyUser['lname'] ?? null]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $mappedId;
    }

    /**
     * @param  array<string, mixed>|null  $legacyUser
     * @param  array<string, mixed>|null  $legacyCustomer
     */
    private function upsertPaymentDetails(int $runId, ?array $legacyUser, ?array $legacyCustomer, ?int $customerId, bool $promote): void
    {
        if (! $legacyUser || ! $legacyCustomer) {
            return;
        }

        $userId = (int) $legacyUser['id'];
        $hasBank = ! empty($legacyCustomer['bank_account_number']) || ! empty($legacyCustomer['account_bank_name']);
        $normalizedPhone = $this->phoneNormalizer->normalize($legacyUser['phone_number'] ?? null);
        $providerCode = $this->phoneNormalizer->inferProvider($normalizedPhone);
        $provider = $providerCode ? WalletProvider::where('code', $providerCode)->first() : null;

        if ($hasBank) {
            $institution = $this->matchFinancialInstitution($legacyCustomer['account_bank_name'] ?? null);
            DB::table('migration_bank_accounts')->updateOrInsert(
                ['migration_run_id' => $runId, 'legacy_user_id' => $userId],
                [
                    'legacy_customer_id' => $legacyCustomer['id'],
                    'mapped_customer_id' => $customerId,
                    'account_number' => $legacyCustomer['bank_account_number'] ?? null,
                    'account_name' => trim(($legacyUser['fname'] ?? '').' '.($legacyUser['lname'] ?? '')),
                    'bank_name' => $legacyCustomer['account_bank_name'] ?? null,
                    'branch_name' => $legacyCustomer['account_branch_name'] ?? null,
                    'migration_status' => $promote ? 'imported' : 'staged',
                    'confidence' => $hasBank && ! empty($legacyCustomer['bank_account_number']) ? 'HIGH' : 'MANUAL_REVIEW',
                    'raw_context' => json_encode(['sort_code' => $legacyCustomer['account_branch_sort_code'] ?? null]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            if ($promote && $customerId && $institution) {
                CustomerPaymentDetail::updateOrCreate(
                    ['customer_id' => $customerId],
                    [
                        'method_type' => 'bank',
                        'bank_financial_institution_id' => $institution->id,
                        'bank_name' => $legacyCustomer['account_bank_name'] ?? $institution->name,
                        'bank_branch' => $legacyCustomer['account_branch_name'] ?? null,
                        'account_name' => trim(($legacyUser['fname'] ?? '').' '.($legacyUser['lname'] ?? '')),
                        'account_number' => $legacyCustomer['bank_account_number'] ?? null,
                    ]
                );
            }
        }

        if ($normalizedPhone) {
            DB::table('migration_wallets')->updateOrInsert(
                ['migration_run_id' => $runId, 'legacy_user_id' => $userId],
                [
                    'legacy_customer_id' => $legacyCustomer['id'],
                    'mapped_customer_id' => $customerId,
                    'wallet_number' => $legacyUser['phone_number'],
                    'wallet_number_normalized' => $normalizedPhone,
                    'provider_code' => $providerCode,
                    'inferred_from' => 'users.phone_number',
                    'migration_status' => $promote ? 'imported' : 'staged',
                    'confidence' => $provider ? 'MEDIUM' : 'LOW',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            if ($promote && $customerId && ! $hasBank) {
                CustomerPaymentDetail::updateOrCreate(
                    ['customer_id' => $customerId],
                    [
                        'method_type' => 'wallet',
                        'wallet_provider_id' => $provider?->id,
                        'wallet_provider' => $provider?->name,
                        'wallet_number' => $normalizedPhone,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    private function promoteLoan(array $loan, int $customerId, LoanProduct $loanProduct, float $effective): int
    {
        $loanNumber = 'LEG-'.$loan['id'];

        $target = Loan::updateOrCreate(
            ['loan_number' => $loanNumber],
            [
                'customer_id' => $customerId,
                'loan_product_id' => $loanProduct->id,
                'principal_amount' => (float) ($loan['obtained_amount'] ?? $loan['loan_amount'] ?? 0),
                'total_amount' => (float) ($loan['loan_amount'] ?? 0),
                'amount_paid' => (float) ($loan['repaid_amount'] ?? 0),
                'outstanding_balance' => $effective,
                'interest_accrued' => max(0, $effective - max(0, (float) ($loan['obtained_amount'] ?? 0) - (float) ($loan['repaid_amount'] ?? 0))),
                'tenure_months' => max(1, (int) ($loan['payment_period'] ?? 1)),
                'loan_start_date' => $loan['created_at'] ? date('Y-m-d', strtotime($loan['created_at'])) : now()->toDateString(),
                'loan_end_date' => ! empty($loan['due_date']) ? date('Y-m-d', strtotime($loan['due_date'])) : null,
                'status' => 'active',
                'disbursement_phone_number' => null,
                'metadata' => [
                    'legacy_loan_id' => $loan['id'],
                    'legacy_user_id' => $loan['user_id'],
                    'legacy_effective_outstanding' => $effective,
                ],
            ]
        );

        return $target->id;
    }

    /**
     * @param  array<string, mixed>  $repayment
     * @param  array<string, mixed>  $classification
     */
    private function promoteRepayment(array $repayment, int $customerId, int $loanId, array $classification, int $pilotLoanId): ?int
    {
        if ($classification['class'] === RepaymentAttributionService::C_AMBIGUOUS) {
            return null;
        }

        $reference = 'LEG-R-'.$repayment['id'];
        $repaymentModel = Repayment::updateOrCreate(
            ['external_reference' => $reference],
            [
                'customer_id' => $customerId,
                'repayment_number' => Repayment::generateRepaymentNumber(),
                'total_amount' => (float) $repayment['repayment_amount'],
                'recovery_method' => 'normal',
                'phone_number' => $repayment['phone_number'] ?? null,
                'status' => 'completed',
                'processed_at' => $repayment['created_at'],
                'metadata' => ['legacy_repayment_id' => $repayment['id']],
            ]
        );

        $amount = (float) $repayment['repayment_amount'];
        if ($classification['class'] === RepaymentAttributionService::A_DIRECT) {
            foreach ($classification['allocations'] as $alloc) {
                if ((int) ($alloc['loan_id'] ?? 0) !== $pilotLoanId) {
                    continue;
                }
                $amount = (float) ($alloc['amount_applied'] ?? $amount);
            }
        }

        $loan = Loan::find($loanId);
        $before = $loan ? (float) $loan->outstanding_balance : $amount;
        $after = max(0, round($before - $amount, 2));

        LoanRepayment::updateOrCreate(
            ['repayment_id' => $repaymentModel->id, 'loan_id' => $loanId],
            [
                'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
                'amount' => $amount,
                'outstanding_balance_before' => $before,
                'outstanding_balance_after' => $after,
            ]
        );

        return $repaymentModel->id;
    }

    /**
     * @param  array<string, mixed>  $repayment
     * @param  array<string, mixed>  $classification
     */
    private function resolvePilotLoanForRepayment($legacy, int $userId, array $repayment, array $classification): ?int
    {
        if ($classification['class'] === RepaymentAttributionService::A_DIRECT) {
            foreach ($classification['allocations'] as $alloc) {
                $loanId = (int) ($alloc['loan_id'] ?? 0);
                if ($loanId && in_array($loanId, self::PILOT_ACTIVE_LOAN_IDS, true)) {
                    return $loanId;
                }
            }
        }

        return $legacy->table('loans')
            ->where('user_id', $userId)
            ->where('status_code', '301')
            ->whereIn('id', self::PILOT_ACTIVE_LOAN_IDS)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $loan
     */
    private function stageLoan(int $runId, array $loan, ?int $targetLoanId, ?float $effective, string $status, ?string $productCode = null): void
    {
        DB::table('migration_loans')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_loan_id' => $loan['id']],
            [
                'legacy_user_id' => $loan['user_id'],
                'mapped_loan_id' => $targetLoanId,
                'legacy_product_type' => null,
                'target_product_code' => $productCode,
                'legacy_effective_outstanding' => $effective ?? $this->balanceCalculator->effectiveOutstanding($loan),
                'target_outstanding' => null,
                'balance_variance' => null,
                'migration_status' => $status,
                'confidence' => $status === 'manual_review' ? 'LOW' : 'HIGH',
                'raw_context' => json_encode(['status_code' => $loan['status_code']]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function matchFinancialInstitution(?string $bankName): ?FinancialInstitution
    {
        if (! $bankName) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $bankName) ?? '');

        return FinancialInstitution::query()
            ->get()
            ->first(function (FinancialInstitution $fi) use ($normalized) {
                $name = strtoupper(preg_replace('/\s+/', '', $fi->name) ?? '');

                return str_contains($normalized, $name) || str_contains($name, $normalized);
            });
    }
}
