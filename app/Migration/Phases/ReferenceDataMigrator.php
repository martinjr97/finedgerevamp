<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\Phases\Support\CompanyMatcher;
use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Migration\Phases\Support\ReferenceMatcher;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LoanProduct;
use App\Models\WalletProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceDataMigrator
{
    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly MigrationEntityMapRepository $maps,
        private readonly CompanyMatcher $companyMatcher,
        private readonly LegacyClientClassifier $clientClassifier,
        private readonly ReferenceMatcher $referenceMatcher,
        private readonly MarketeerReferenceMigrator $marketeerMigrator,
        private readonly ProductCustomerGroupReferenceMigrator $customerGroupMigrator,
        private readonly BranchRelationshipManagerReferenceMigrator $branchRmMigrator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(bool $promote = false, ?string $only = null, ?string $runUuid = null): array
    {
        if ($promote) {
            if ($only === 'marketeer') {
                $this->assertMarketeerReferencePromoteSafe();
            } else {
                app(MigrationPromotionGate::class)->assertReferenceDataPromote();
            }
        }

        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $run = $this->runManager->start('m2-reference', $only ?: 'full', $runUuid);
        $runId = $run['id'];

        $stats = [
            'run_uuid' => $run['run_uuid'],
            'migration_run_id' => $runId,
            'promote' => $promote,
            'products' => ['MATCHED_EXISTING' => 0, 'CREATED' => 0, 'SKIPPED' => 0],
            'companies' => [
                'MATCHED_EXISTING' => 0,
                'CREATED' => 0,
                'WOULD_CREATE' => 0,
                'SKIP_GOVERNMENT_PLACEHOLDER' => 0,
                'SKIP_MARKETEER_PLACEHOLDER' => 0,
                'SKIP_CHARACTER_PLACEHOLDER' => 0,
                'SKIP_UNUSED' => 0,
                'MANUAL_REVIEW' => 0,
            ],
            'banks' => ['MATCHED_EXISTING' => 0, 'SKIP_TREASURY' => 0, 'MANUAL_REVIEW' => 0, 'WOULD_CREATE' => 0],
            'wallet_providers' => ['MATCHED_EXISTING' => 0, 'CREATED' => 0, 'SKIPPED' => 0, 'MANUAL_REVIEW' => 0, 'WOULD_CREATE' => 0],
            'branches' => ['MATCHED_EXISTING' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0, 'MANUAL_REVIEW' => 0],
            'relationship_managers' => ['MATCHED_EXISTING' => 0, 'CREATED' => 0, 'WOULD_CREATE' => 0, 'MANUAL_REVIEW' => 0],
            'marketeer' => [
                'groups' => ['MATCHED_EXISTING' => 0, 'WOULD_CREATE' => 0, 'CREATED' => 0, 'MANUAL_REVIEW' => 0],
                'markets' => ['MATCHED_EXISTING' => 0, 'WOULD_CREATE' => 0, 'CREATED' => 0, 'SKIP_UNUSED' => 0, 'MANUAL_REVIEW' => 0],
            ],
            'government_placeholders_skipped' => 0,
            'marketeer_placeholders_skipped' => 0,
            'customer_groups' => [
                'GOVERNMENT_MATCHED' => 0,
                'GOVERNMENT_CREATED' => 0,
                'GOVERNMENT_WOULD_CREATE' => 0,
                'GOVERNMENT_MANUAL_REVIEW' => 0,
                'CHARACTER_MATCHED' => 0,
                'CHARACTER_CREATED' => 0,
                'CHARACTER_WOULD_CREATE' => 0,
                'CHARACTER_MANUAL_REVIEW' => 0,
            ],
        ];

        if (! $only || $only === 'products') {
            $this->migrateProducts($runId, $promote, $stats);
        }

        if (! $only || $only === 'companies') {
            $this->migrateCompanies($legacy, $runId, $promote, $stats);
        }

        if (! $only || $only === 'banks') {
            $this->migrateBanks($legacy, $runId, $promote, $stats);
        }

        if (! $only || $only === 'wallet_providers' || $only === 'providers') {
            $this->migrateWalletProviders($legacy, $runId, $promote, $stats);
        }

        if (! $only || $only === 'branches' || $only === 'relationship_managers') {
            $this->branchRmMigrator->migrate($legacy, $runId, $promote, $stats);
        }

        if (! $only || $only === 'marketeer') {
            $this->marketeerMigrator->migrate($legacy, $runId, $promote, $stats);
        }

        if (! $only || $only === 'customer_groups' || $only === 'groups') {
            $this->customerGroupMigrator->migrate($legacy, $runId, $promote, $stats);
        }

        $this->runManager->complete($runId, $stats);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateProducts(int $runId, bool $promote, array &$stats): void
    {
        $definitions = [
            'MOU-001' => ['name' => 'MOU Salary Loan', 'category' => 'mou'],
            'GOV-001' => ['name' => 'Government Loan', 'category' => 'government'],
            'CHAR-001' => ['name' => 'Character Loan', 'category' => 'character'],
            'MARK-001' => ['name' => 'Marketeer Loan', 'category' => 'marketeer'],
        ];

        foreach ($definitions as $code => $def) {
            $existing = LoanProduct::query()->where('code', $code)->first();
            if ($existing) {
                $stats['products']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_PRODUCT,
                    $code,
                    LoanProduct::class,
                    $existing->id,
                    'existing_target',
                    'HIGH',
                    null,
                    $runId
                );

                continue;
            }

            if (! $promote) {
                $stats['products']['SKIPPED']++;

                continue;
            }

            $companyId = Company::query()->value('id') ?? 1;
            $product = LoanProduct::create([
                'company_id' => $companyId,
                'name' => $def['name'],
                'code' => $code,
                'category' => $def['category'],
                'description' => "Migrated {$def['category']} product",
                'tenure_months' => 3,
                'max_amount' => 500_000,
                'requires_collateral' => false,
                'requires_reference' => false,
                'is_active' => true,
                'rules' => [],
            ]);
            $stats['products']['CREATED']++;
            $this->maps->store(MigrationEntityMapRepository::TYPE_PRODUCT, $code, LoanProduct::class, $product->id, 'created', 'HIGH', null, $runId);
            $this->maps->trackCreated($runId, LoanProduct::class, $product->id);
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateCompanies($legacy, int $runId, bool $promote, array &$stats): void
    {
        $clients = $legacy->table('clients')->get();
        $allLoans = $legacy->table('loans')->get()->groupBy('client_id');

        foreach ($clients as $client) {
            $clientArr = (array) $client;
            $legacyId = (int) $clientArr['id'];
            $clientLoans = $allLoans->get($legacyId, collect());
            $customerCount = (int) $legacy->table('customers')->where('client_id', $legacyId)->count();

            $classification = $this->clientClassifier->classify($clientArr, $clientLoans, $customerCount);

            if ($this->clientClassifier->isProductPlaceholderLegacyClientId($legacyId)
                || ! $this->clientClassifier->shouldCreateOrMatchCompany($classification)) {
                $skipKey = $this->clientClassifier->companySkipReason($classification);
                if ($skipKey) {
                    $stats['companies'][$skipKey]++;
                }
                if ($classification === LegacyClientClassifier::GOVERNMENT_PRODUCT_PLACEHOLDER) {
                    $stats['government_placeholders_skipped']++;
                }
                if ($classification === LegacyClientClassifier::MARKETEER_PRODUCT_PLACEHOLDER) {
                    $stats['marketeer_placeholders_skipped']++;
                }
                $this->stageCompany($runId, $legacyId, null, strtolower($skipKey), $classification);

                continue;
            }

            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_COMPANY, (string) $legacyId);
            if ($existingMap && ! $this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $legacyId)) {
                $stats['companies']['MATCHED_EXISTING']++;
                $this->stageCompany($runId, $legacyId, (int) $existingMap->target_id, 'matched_existing', $existingMap->mapping_method);

                continue;
            }

            $match = $this->companyMatcher->matchExisting($clientArr);
            if ($match['company']) {
                $stats['companies']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_COMPANY,
                    (string) $legacyId,
                    Company::class,
                    $match['company']->id,
                    $match['method'] ?? 'matched',
                    $match['confidence'],
                    null,
                    $runId
                );
                $this->stageCompany($runId, $legacyId, $match['company']->id, 'matched_existing', $match['method']);

                continue;
            }

            if (! $promote) {
                $stats['companies']['WOULD_CREATE']++;
                $this->stageCompany($runId, $legacyId, null, 'would_create', 'new_company');

                continue;
            }

            $company = Company::create([
                'name' => $clientArr['company_name'] ?? ('Legacy Client '.$legacyId),
                'slug' => Str::slug($clientArr['company_name'] ?? 'legacy-'.$legacyId),
                'code' => 'LEG-'.$legacyId,
                'type' => 'partner',
                'registration_number' => $clientArr['reg_number'] ?? null,
                'contact_email' => $clientArr['email'] ?? null,
                'contact_phone' => $clientArr['primary_number'] ?? null,
                'address_line1' => $clientArr['address'] ?? null,
                'city' => $clientArr['town'] ?? null,
                'state' => $clientArr['province'] ?? null,
                'country' => $clientArr['country'] ?? 'Zambia',
                'pay_day' => is_numeric($clientArr['pay_date'] ?? null) ? (int) $clientArr['pay_date'] : null,
                'monthly_cut_off_day' => is_numeric($clientArr['cut_off_date'] ?? null) ? (int) $clientArr['cut_off_date'] : null,
                'relationship_manager_id' => $this->mappedRelationshipManagerAdminId($clientArr),
                'status' => 'active',
                'approval_status' => 'approved',
                'settings' => ['legacy_client_id' => $legacyId, 'legacy_product_type' => $clientArr['product_type'] ?? null],
            ]);

            $stats['companies']['CREATED']++;
            $this->maps->store(MigrationEntityMapRepository::TYPE_COMPANY, (string) $legacyId, Company::class, $company->id, 'created', 'HIGH', null, $runId);
            $this->maps->trackCreated($runId, Company::class, $company->id);
            $this->stageCompany($runId, $legacyId, $company->id, 'created', 'created');
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateBanks($legacy, int $runId, bool $promote, array &$stats): void
    {
        foreach ($legacy->table('banks')->get() as $bank) {
            $bankArr = (array) $bank;
            $legacyId = (string) ($bankArr['id'] ?? $bankArr['bank_code'] ?? uniqid());
            $treasury = $this->referenceMatcher->matchTreasuryBank($bankArr);
            if ($treasury) {
                $stats['banks']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_BANK,
                    $legacyId,
                    \App\Models\Bank::class,
                    $treasury->id,
                    'treasury_bank_matched',
                    'HIGH',
                    null,
                    $runId
                );
                $this->stageFinancialInstitution($runId, $legacyId, $bankArr, $treasury->id, 'matched_existing', 'HIGH', null);

                continue;
            }

            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_FINANCIAL_INSTITUTION, $legacyId);
            if ($existingMap) {
                $stats['banks']['MATCHED_EXISTING']++;
                $this->stageFinancialInstitution($runId, $legacyId, $bankArr, (int) $existingMap->target_id, 'matched_existing', 'HIGH', null);

                continue;
            }

            $result = $this->referenceMatcher->resolveFinancialInstitution($bankArr);
            if ($result->isConflict()) {
                $stats['banks']['MANUAL_REVIEW']++;
                $this->stageFinancialInstitution(
                    $runId,
                    $legacyId,
                    $bankArr,
                    null,
                    'manual_review',
                    'LOW',
                    $result->reason,
                    $result->candidateTargetIds
                );

                continue;
            }

            if ($result->isMatched() && $result->target instanceof FinancialInstitution) {
                $stats['banks']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_FINANCIAL_INSTITUTION,
                    $legacyId,
                    FinancialInstitution::class,
                    $result->target->id,
                    $result->method,
                    'HIGH',
                    null,
                    $runId
                );
                $this->stageFinancialInstitution($runId, $legacyId, $bankArr, $result->target->id, 'matched_existing', 'HIGH', null);

                continue;
            }

            $stats['banks']['MANUAL_REVIEW']++;
            $this->stageFinancialInstitution($runId, $legacyId, $bankArr, null, 'manual_review', 'LOW', 'no_target_match');
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function migrateWalletProviders($legacy, int $runId, bool $promote, array &$stats): void
    {
        $tables = ['payment_wallets', 'wallet_providers'];
        $rows = collect();
        foreach ($tables as $table) {
            try {
                $rows = $rows->merge($legacy->table($table)->get());
            } catch (\Throwable) {
                // table may not exist in all legacy snapshots
            }
        }

        foreach ($rows->unique('id') as $wallet) {
            $walletArr = (array) $wallet;
            if (strtoupper($walletArr['code'] ?? '') === 'OTHER') {
                $stats['wallet_providers']['SKIPPED']++;

                continue;
            }

            if ($this->referenceMatcher->isTreasuryWallet($walletArr)) {
                $stats['wallet_providers']['SKIPPED']++;

                continue;
            }

            $legacyId = (string) ($walletArr['id'] ?? uniqid());
            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_WALLET_PROVIDER, $legacyId);
            if ($existingMap) {
                $stats['wallet_providers']['MATCHED_EXISTING']++;
                $this->stageWalletProvider($runId, $legacyId, $walletArr, (int) $existingMap->target_id, 'matched_existing', 'HIGH', null);

                continue;
            }

            $result = $this->referenceMatcher->resolveWalletProvider($walletArr);
            if ($result->isConflict()) {
                $stats['wallet_providers']['MANUAL_REVIEW']++;
                $this->stageWalletProvider(
                    $runId,
                    $legacyId,
                    $walletArr,
                    null,
                    'manual_review',
                    'LOW',
                    $result->reason,
                    $result->candidateTargetIds
                );

                continue;
            }

            if ($result->isMatched() && $result->target instanceof WalletProvider) {
                $stats['wallet_providers']['MATCHED_EXISTING']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_WALLET_PROVIDER,
                    $legacyId,
                    WalletProvider::class,
                    $result->target->id,
                    $result->method,
                    'HIGH',
                    null,
                    $runId
                );
                $this->stageWalletProvider($runId, $legacyId, $walletArr, $result->target->id, 'matched_existing', 'HIGH', null);

                continue;
            }

            if (! $promote) {
                $stats['wallet_providers']['WOULD_CREATE']++;
                $this->stageWalletProvider($runId, $legacyId, $walletArr, null, 'would_create', 'MEDIUM', null);

                continue;
            }

            $code = strtoupper($walletArr['code'] ?? 'LEGWP'.$legacyId);
            $codeAliases = [
                'AIRTEL' => 'AIRTEL_MONEY',
                'MTN' => 'MTN_MONEY',
                'ZAMTEL' => 'ZAMTEL_MONEY',
            ];
            $lookupCode = $codeAliases[$code] ?? $code;
            if (WalletProvider::query()->where('code', $lookupCode)->exists()) {
                $stats['wallet_providers']['MANUAL_REVIEW']++;
                $this->stageWalletProvider(
                    $runId,
                    $legacyId,
                    $walletArr,
                    null,
                    'manual_review',
                    'LOW',
                    'target_code_exists_without_map',
                    WalletProvider::query()->where('code', $lookupCode)->pluck('id')->all()
                );

                continue;
            }

            $provider = WalletProvider::create([
                'name' => $walletArr['name'] ?? $walletArr['wallet_name'] ?? 'Legacy Provider',
                'code' => $lookupCode,
                'is_active' => true,
            ]);
            $stats['wallet_providers']['CREATED']++;
            $this->maps->store(MigrationEntityMapRepository::TYPE_WALLET_PROVIDER, $legacyId, WalletProvider::class, $provider->id, 'created', 'MEDIUM', null, $runId);
            $this->maps->trackCreated($runId, WalletProvider::class, $provider->id);
            $this->stageWalletProvider($runId, $legacyId, $walletArr, $provider->id, 'created', 'MEDIUM', null);
        }

        foreach (['MTN_MONEY', 'AIRTEL_MONEY', 'ZAMTEL_MONEY'] as $code) {
            $existing = WalletProvider::query()->where('code', $code)->first();
            if ($existing && ! $this->maps->find(MigrationEntityMapRepository::TYPE_WALLET_PROVIDER, $code)) {
                $this->maps->store(MigrationEntityMapRepository::TYPE_WALLET_PROVIDER, $code, WalletProvider::class, $existing->id, 'existing_target', 'HIGH', null, $runId);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $legacyClient
     */
    private function mappedRelationshipManagerAdminId(array $legacyClient): ?int
    {
        $legacyRmId = $legacyClient['relationship_manager'] ?? null;
        if (! is_numeric($legacyRmId) || (int) $legacyRmId <= 0) {
            return null;
        }

        return $this->maps->targetId(
            MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER,
            (string) $legacyRmId
        );
    }

    /**
     * @param  array<string, mixed>  $bankArr
     * @param  list<int>  $candidateTargetIds
     */
    private function stageFinancialInstitution(
        int $runId,
        string $legacyId,
        array $bankArr,
        ?int $mappedId,
        string $status,
        string $confidence,
        ?string $exception,
        array $candidateTargetIds = [],
    ): void {
        DB::table('migration_financial_institutions')->updateOrInsert(
            [
                'migration_run_id' => $runId,
                'legacy_identifier' => $legacyId,
            ],
            [
                'legacy_bank_id' => is_numeric($bankArr['id'] ?? null) ? (int) $bankArr['id'] : null,
                'mapped_financial_institution_id' => $mappedId,
                'migration_status' => $status,
                'confidence' => $confidence,
                'exception' => $exception,
                'raw_context' => json_encode([
                    'legacy' => $bankArr,
                    'candidate_target_ids' => $candidateTargetIds,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $walletArr
     * @param  list<int>  $candidateTargetIds
     */
    private function stageWalletProvider(
        int $runId,
        string $legacyId,
        array $walletArr,
        ?int $mappedId,
        string $status,
        string $confidence,
        ?string $exception,
        array $candidateTargetIds = [],
    ): void {
        DB::table('migration_wallet_providers')->updateOrInsert(
            [
                'migration_run_id' => $runId,
                'legacy_identifier' => $legacyId,
            ],
            [
                'legacy_wallet_id' => is_numeric($walletArr['id'] ?? null) ? (int) $walletArr['id'] : null,
                'mapped_wallet_provider_id' => $mappedId,
                'migration_status' => $status,
                'confidence' => $confidence,
                'exception' => $exception,
                'raw_context' => json_encode([
                    'legacy' => $walletArr,
                    'candidate_target_ids' => $candidateTargetIds,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function stageCompany(int $runId, int $legacyId, ?int $mappedId, string $status, string $strategy): void
    {
        DB::table('migration_companies')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_client_id' => $legacyId],
            [
                'mapped_company_id' => $mappedId,
                'match_strategy' => $strategy,
                'migration_status' => $status,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function assertMarketeerReferencePromoteSafe(): void
    {
        $product = LoanProduct::query()->where('code', 'MARK-001')->first();
        if (! $product) {
            throw new \RuntimeException('MARK-001 must exist before Marketeer reference promote.');
        }

        if ($this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, '36')) {
            return;
        }

        if ($this->maps->find(MigrationEntityMapRepository::TYPE_COMPANY, '36')
            && ! $this->companyMatcher->isMarketeerPlaceholderClient(['product_type' => 'marketize_based'])) {
            // placeholder check passed in migrateCompanies — map may exist from pilot
        }
    }
}
