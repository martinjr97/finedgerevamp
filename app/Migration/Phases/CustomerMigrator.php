<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\LegacyProductMapper;
use App\Migration\PhoneNormalizer;
use App\Migration\Phases\Support\CompanyMatcher;
use App\Migration\Phases\Support\CustomerBranchResolver;
use App\Migration\Phases\Support\CustomerIdentityResolutionRegistry;
use App\Migration\Phases\Support\CustomerIdentityResolver;
use App\Migration\Phases\Support\CustomerMigrationProfileResolver;
use App\Migration\Phases\Support\CustomerMatcher;
use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Migration\Phases\Support\MarketeerClassifier;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\MarketeerCustomerDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerMigrator
{
    private const GOV_CLIENT_ID = 8;

    public function __construct(
        private readonly MigrationRunManager $runManager,
        private readonly MigrationEntityMapRepository $maps,
        private readonly MigrationDependencyGate $gate,
        private readonly MigrationPromotionGate $promotionGate,
        private readonly CustomerMatcher $customerMatcher,
        private readonly CustomerIdentityResolver $identityResolver,
        private readonly CompanyMatcher $companyMatcher,
        private readonly LegacyClientClassifier $clientClassifier,
        private readonly MarketeerClassifier $marketeerClassifier,
        private readonly CustomerMigrationProfileResolver $profileResolver,
        private readonly ProductCustomerGroupReferenceMigrator $customerGroupMigrator,
        private readonly LegacyProductMapper $productMapper,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly CustomerBranchResolver $branchResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(
        bool $promote = false,
        ?int $legacyUserId = null,
        ?int $limit = null,
        ?string $runUuid = null,
        bool $correctExisting = false,
    ): array {
        if ($promote && ! $correctExisting) {
            $this->promotionGate->assertCustomersPromote();
        }

        $this->gate->requireProductsMapped();

        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $run = $this->runManager->start('m2-customers', $legacyUserId ? 'single' : 'all', $runUuid);
        $runId = $run['id'];

        $query = $legacy->table('customers')->orderBy('user_id');
        if ($legacyUserId) {
            $query->where('user_id', $legacyUserId);
        }
        if ($limit) {
            $query->limit($limit);
        }

        $stats = [
            'run_uuid' => $run['run_uuid'],
            'migration_run_id' => $runId,
            'promote' => $promote,
            'legacy_users_total' => (int) $legacy->table('users')->count(),
            'true_customers_identified' => 0,
            'excluded_admin_staff_users' => (int) $legacy->table('users')->whereNotIn('id', $legacy->table('customers')->pluck('user_id'))->count(),
            'would_create' => 0,
            'created' => 0,
            'matched_existing' => 0,
            'manual_review' => 0,
            'possible_duplicate' => 0,
            'broken_user_relation' => 0,
            'company_linked' => 0,
            'mou_company_linked' => 0,
            'government_intentional_no_company' => 0,
            'character_intentional_no_company' => 0,
            'marketeer_intentional_no_company' => 0,
            'other_legitimate_no_company' => 0,
            'company_mapping_pending' => 0,
            'company_manual_review' => 0,
            'no_company_legitimate' => 0,
            'marketeer_customers' => 0,
            'marketeer_market_mapped' => 0,
            'marketeer_market_pending' => 0,
            'marketeer_missing_market' => 0,
            'marketeer_incorrect_company_link' => 0,
            'identity_alias_resolved' => 0,
            'identity_alias_groups' => count(CustomerIdentityResolutionRegistry::approved()),
            'unique_intended_target_customers' => 0,
            'with_bank_account' => 0,
            'with_wallet' => 0,
            'branch_assigned' => 0,
            'branch_head_office_fallback' => 0,
            'corrected_existing' => 0,
            'correct_existing_mode' => $correctExisting,
        ];

        if ($promote || $correctExisting) {
            $groupStats = [];
            $this->customerGroupMigrator->migrate($legacy, $runId, true, $groupStats);
        }

        $rows = $query->get()->sortBy(function ($row) {
            $userId = (int) $row->user_id;
            if (CustomerIdentityResolutionRegistry::isAlias($userId)) {
                return 1_000_000 + $userId;
            }

            return $userId;
        });

        $runCache = [];

        foreach ($rows as $customerRow) {
            $legacyCustomer = (array) $customerRow;
            $userId = (int) $legacyCustomer['user_id'];
            $stats['true_customers_identified']++;

            $legacyUser = (array) $legacy->table('users')->where('id', $userId)->first();
            if ($legacyUser === []) {
                $stats['broken_user_relation']++;
                $stats['manual_review']++;
                $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'manual_review', null, 'missing_user_row');

                continue;
            }

            $legacyClient = $legacyCustomer['client_id']
                ? (array) $legacy->table('clients')->where('id', $legacyCustomer['client_id'])->first()
                : null;

            $clientId = (int) ($legacyCustomer['client_id'] ?? 0);
            $clientLoans = $clientId
                ? $legacy->table('loans')->where('client_id', $clientId)->get()
                : collect();

            $mouCompanyId = null;
            if ($clientId) {
                $mouCompanyId = $this->maps->targetId(
                    MigrationEntityMapRepository::TYPE_COMPANY,
                    (string) $clientId
                );
                if ($this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $clientId)) {
                    $mouCompanyId = null;
                }
            }

            $profile = $this->profileResolver->resolve(
                $legacyCustomer,
                $legacyClient,
                $clientLoans,
                $mouCompanyId
            );
            $productMap = ['code' => $profile['product_code']];

            $this->classifyCompanyCoverage($stats, $legacyCustomer, $legacyClient);
            $this->classifyMarketeer($stats, $legacyCustomer, $legacyClient);

            $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_CUSTOMER, (string) $userId);
            if ($existingMap) {
                $stats['matched_existing']++;
                $runCache[$userId] = (int) $existingMap->target_id;
                if ($promote || $correctExisting) {
                    $this->syncMigratedCustomer(
                        (int) $existingMap->target_id,
                        $profile,
                        $legacyCustomer,
                        $legacyUser,
                        $legacyClient,
                        $legacy,
                        $runId,
                        $userId
                    );
                    $stats['corrected_existing']++;
                }
                $this->stageCustomer($runId, $userId, $legacyCustomer, (int) $existingMap->target_id, 'matched_existing', $productMap['code']);

                continue;
            }

            if ($correctExisting) {
                continue;
            }

            if (CustomerIdentityResolutionRegistry::isAlias($userId)) {
                $aliasTarget = $this->identityResolver->resolveTargetForUser($userId, $runCache);
                if ($aliasTarget && $aliasTarget > 0) {
                    $stats['identity_alias_resolved']++;
                    $stats['matched_existing']++;
                    if ($promote) {
                        $this->maps->store(
                            MigrationEntityMapRepository::TYPE_CUSTOMER,
                            (string) $userId,
                            Customer::class,
                            $aliasTarget,
                            'identity_resolution_alias',
                            'HIGH',
                            (string) ($legacyCustomer['id'] ?? ''),
                            $runId,
                            ['role' => 'alias', 'primary_legacy_user_id' => CustomerIdentityResolutionRegistry::primaryUserId($userId)]
                        );
                    }
                    $runCache[$userId] = $aliasTarget;
                    $this->stageCustomer($runId, $userId, $legacyCustomer, $aliasTarget, 'matched_existing', $productMap['code'], 'identity_alias');

                    continue;
                }

                $primaryId = CustomerIdentityResolutionRegistry::primaryUserId($userId);
                if (! $promote && $primaryId && isset($runCache[$primaryId])) {
                    $stats['identity_alias_resolved']++;
                    $stats['matched_existing']++;
                    $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'matched_existing', $productMap['code'], 'identity_alias_pending_primary');

                    continue;
                }
            }

            $match = $this->customerMatcher->matchExisting($legacyUser, $legacyCustomer);
            if ($match['status'] === 'POSSIBLE_DUPLICATE') {
                $stats['possible_duplicate']++;
                $stats['manual_review']++;
                $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'manual_review', $productMap['code'], $match['method']);

                continue;
            }

            if ($match['customer']) {
                if ($match['method'] === 'email' || $match['confidence'] === 'MEDIUM') {
                    $stats['manual_review']++;
                    $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'manual_review', $productMap['code'], 'uncertain_'.$match['method']);

                    continue;
                }
                $stats['matched_existing']++;
                $this->maps->store(
                    MigrationEntityMapRepository::TYPE_CUSTOMER,
                    (string) $userId,
                    Customer::class,
                    $match['customer']->id,
                    $match['method'] ?? 'matched',
                    $match['confidence'],
                    (string) ($legacyCustomer['id'] ?? ''),
                    $runId
                );
                $this->stageCustomer($runId, $userId, $legacyCustomer, $match['customer']->id, 'matched_existing', $productMap['code']);

                continue;
            }

            if (! $promote) {
                $stats['would_create']++;
                $runCache[$userId] = -1;
                $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'would_create', $productMap['code']);

                continue;
            }

            $loanProduct = LoanProduct::query()->where('code', $profile['product_code'])->first();
            if (! $loanProduct) {
                $stats['manual_review']++;
                $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'manual_review', $profile['product_code'], 'missing_product');

                continue;
            }

            $companyId = $profile['company_id'];
            $customerGroupId = $profile['customer_group_id'];
            $targetMarketId = null;

            if ($profile['requires_market_detail']) {
                $companyId = null;
                $customerGroupId = $this->maps->targetId(
                    MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
                    MarketeerReferenceMigrator::DEFAULT_GROUP_CODE
                );
                $legacyMarketId = $this->marketeerClassifier->legacyMarketId($legacyCustomer);
                if ($legacyMarketId) {
                    $targetMarketId = $this->maps->targetId(
                        MigrationEntityMapRepository::TYPE_MARKET,
                        (string) $legacyMarketId
                    );
                }

                if (! $targetMarketId) {
                    $stats['manual_review']++;
                    $this->stageCustomer($runId, $userId, $legacyCustomer, null, 'manual_review', $profile['product_code'], 'marketeer_missing_market');

                    continue;
                }
            }

            $email = $this->resolveMigrationEmail($legacyUser, $userId);
            $branchResolution = $this->branchResolver->resolve($legacyCustomer, $legacyClient);
            $this->trackBranchResolution($stats, $branchResolution);

            $customer = Customer::create([
                'company_id' => $companyId,
                'customer_group_id' => $customerGroupId,
                'loan_product_id' => $loanProduct->id,
                'branch_id' => $branchResolution['branch_id'],
                'first_name' => $legacyUser['fname'] ?? 'Legacy',
                'last_name' => $legacyUser['lname'] ?? 'Customer',
                'email' => $email,
                'phone' => $this->resolveMigrationPhone($legacyUser, $userId),
                'national_id' => $legacyCustomer['nrc'] ?? $legacyUser['nrc'] ?? null,
                'employee_number' => $legacyUser['emp_number'] ?? null,
                'gross_salary' => is_numeric($legacyCustomer['gross_salary'] ?? null) ? $legacyCustomer['gross_salary'] : null,
                'net_salary' => is_numeric($legacyCustomer['net_pay'] ?? null) ? $legacyCustomer['net_pay'] : null,
                'status' => 'active',
                'kyc_status' => 'verified',
                'password' => bcrypt('Migration!'.Str::random(12)),
                'metadata' => [
                    'legacy_user_id' => $userId,
                    'legacy_customer_id' => $legacyCustomer['id'] ?? null,
                    'legacy_client_id' => $legacyCustomer['client_id'] ?? null,
                    'legacy_market_id' => $legacyCustomer['market_id'] ?? null,
                    'source_system' => 'finedge_legacy',
                    'legacy_default_product_code' => $profile['product_code'],
                    'client_classification' => $profile['client_classification'],
                    'is_marketeer' => $profile['requires_market_detail'],
                    'legacy_relationship_manager_id' => $branchResolution['legacy_relationship_manager_id'],
                    'branch_resolution' => $branchResolution['resolution'],
                ],
            ]);

            if ($profile['requires_market_detail'] && $targetMarketId) {
                MarketeerCustomerDetail::create([
                    'customer_id' => $customer->id,
                    'market_id' => $targetMarketId,
                    'monthly_income' => is_numeric($legacyCustomer['gross_salary'] ?? null)
                        ? $legacyCustomer['gross_salary']
                        : null,
                    'stand_description' => $legacyCustomer['purpose'] ?? null,
                ]);
                $this->maps->trackCreated($runId, MarketeerCustomerDetail::class, $customer->id);
            }

            $stats['created']++;
            $this->maps->store(
                MigrationEntityMapRepository::TYPE_CUSTOMER,
                (string) $userId,
                Customer::class,
                $customer->id,
                'created',
                'HIGH',
                (string) ($legacyCustomer['id'] ?? ''),
                $runId
            );
            $this->maps->trackCreated($runId, Customer::class, $customer->id);
            $runCache[$userId] = $customer->id;
            $this->stageCustomer($runId, $userId, $legacyCustomer, $customer->id, 'created', $productMap['code']);

            if (! empty($legacyCustomer['bank_account_number'])) {
                $stats['with_bank_account']++;
            }
            if ($this->phoneNormalizer->normalize($legacyUser['phone_number'] ?? null)) {
                $stats['with_wallet']++;
            }
        }

        $stats['unique_intended_target_customers'] = $stats['true_customers_identified']
            - CustomerIdentityResolutionRegistry::aliasLegacyUserCount();

        $stats['reconciles'] = $stats['true_customers_identified'] === (
            $stats['would_create'] + $stats['created'] + $stats['matched_existing'] + $stats['manual_review'] + $stats['broken_user_relation']
        ) && $stats['true_customers_identified'] === (
            ($stats['mou_company_linked'] ?? 0)
            + ($stats['government_intentional_no_company'] ?? 0)
            + ($stats['character_intentional_no_company'] ?? 0)
            + ($stats['marketeer_intentional_no_company'] ?? 0)
            + ($stats['other_legitimate_no_company'] ?? 0)
            + ($stats['company_mapping_pending'] ?? 0)
            + ($stats['company_manual_review'] ?? 0)
        );

        $this->runManager->complete($runId, $stats);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>|null  $legacyClient
     */
    private function classifyCompanyCoverage(array &$stats, array $legacyCustomer, ?array $legacyClient): void
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();
        $clientId = (int) ($legacyCustomer['client_id'] ?? 0);
        $isMarketeer = $this->marketeerClassifier->isMarketeerCustomer($legacyCustomer, $legacyClient);

        if ($isMarketeer) {
            $stats['marketeer_intentional_no_company']++;

            return;
        }

        if (! $legacyClient) {
            $stats['company_manual_review']++;

            return;
        }

        $clientLoans = $legacy->table('loans')->where('client_id', $clientId)->get();
        $customerCount = (int) $legacy->table('customers')->where('client_id', $clientId)->count();
        $classification = $this->clientClassifier->classify($legacyClient, $clientLoans, max(1, $customerCount));

        $hasMap = $clientId
            && $this->maps->targetId(MigrationEntityMapRepository::TYPE_COMPANY, (string) $clientId)
            && ! $this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $clientId);

        $bucket = $this->clientClassifier->customerCompanyBucket($classification, false, $hasMap);

        switch ($bucket) {
            case 'GOVERNMENT_INTENTIONAL_NO_COMPANY':
                $stats['government_intentional_no_company']++;
                break;
            case 'CHARACTER_INTENTIONAL_NO_COMPANY':
                $stats['character_intentional_no_company']++;
                break;
            case 'MARKETEER_INTENTIONAL_NO_COMPANY':
                $stats['marketeer_intentional_no_company']++;
                break;
            case 'MOU_COMPANY_LINKED':
                $stats['mou_company_linked']++;
                $stats['company_linked']++;
                break;
            case 'COMPANY_MAPPING_PENDING':
                $stats['company_mapping_pending']++;
                break;
            case 'OTHER_LEGITIMATE_NO_COMPANY':
                $stats['other_legitimate_no_company']++;
                break;
            case 'MANUAL_REVIEW':
                $stats['company_manual_review']++;
                break;
            default:
                $stats['company_manual_review']++;
                break;
        }

        $stats['no_company_legitimate'] = ($stats['government_intentional_no_company'] ?? 0)
            + ($stats['character_intentional_no_company'] ?? 0)
            + ($stats['marketeer_intentional_no_company'] ?? 0)
            + ($stats['other_legitimate_no_company'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>|null  $legacyClient
     */
    private function classifyMarketeer(array &$stats, array $legacyCustomer, ?array $legacyClient): void
    {
        if (! $this->marketeerClassifier->isMarketeerCustomer($legacyCustomer, $legacyClient)) {
            return;
        }

        $stats['marketeer_customers']++;

        $legacyMarketId = $this->marketeerClassifier->legacyMarketId($legacyCustomer);
        if (! $legacyMarketId) {
            $stats['marketeer_missing_market']++;

            return;
        }

        if ($this->maps->targetId(MigrationEntityMapRepository::TYPE_MARKET, (string) $legacyMarketId)) {
            $stats['marketeer_market_mapped']++;
        } else {
            $stats['marketeer_market_pending']++;
        }

        $clientId = (int) ($legacyCustomer['client_id'] ?? 0);
        if ($clientId && ! $this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $clientId)
            && $this->maps->targetId(MigrationEntityMapRepository::TYPE_COMPANY, (string) $clientId)) {
            $stats['marketeer_incorrect_company_link']++;
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $legacyCustomer
     * @param  array<string, mixed>  $legacyUser
     * @param  array<string, mixed>|null  $legacyClient
     */
    private function syncMigratedCustomer(
        int $customerId,
        array $profile,
        array $legacyCustomer,
        array $legacyUser,
        ?array $legacyClient,
        $legacy,
        int $runId,
        int $userId,
    ): void {
        $loanProduct = LoanProduct::query()->where('code', $profile['product_code'])->first();
        $customer = Customer::query()->find($customerId);
        if (! $loanProduct || ! $customer) {
            return;
        }

        $companyId = $profile['company_id'];
        $customerGroupId = $profile['customer_group_id'];

        if ($profile['requires_market_detail']) {
            $companyId = null;
            $customerGroupId = $this->maps->targetId(
                MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP,
                MarketeerReferenceMigrator::DEFAULT_GROUP_CODE
            );
            $legacyMarketId = $this->marketeerClassifier->legacyMarketId($legacyCustomer);
            if ($legacyMarketId) {
                $targetMarketId = $this->maps->targetId(
                    MigrationEntityMapRepository::TYPE_MARKET,
                    (string) $legacyMarketId
                );
                if ($targetMarketId) {
                    MarketeerCustomerDetail::query()->updateOrCreate(
                        ['customer_id' => $customer->id],
                        [
                            'market_id' => $targetMarketId,
                            'monthly_income' => is_numeric($legacyCustomer['gross_salary'] ?? null)
                                ? $legacyCustomer['gross_salary']
                                : null,
                            'stand_description' => $legacyCustomer['purpose'] ?? null,
                        ]
                    );
                }
            }
        }

        $branchResolution = $this->branchResolver->resolve($legacyCustomer, $legacyClient);

        $customer->update([
            'loan_product_id' => $loanProduct->id,
            'company_id' => $companyId,
            'customer_group_id' => $customerGroupId,
            'branch_id' => $branchResolution['branch_id'],
            'metadata' => array_merge($customer->metadata ?? [], [
                'legacy_user_id' => $userId,
                'legacy_customer_id' => $legacyCustomer['id'] ?? null,
                'legacy_client_id' => $legacyCustomer['client_id'] ?? null,
                'legacy_default_product_code' => $profile['product_code'],
                'client_classification' => $profile['client_classification'],
                'is_marketeer' => $profile['requires_market_detail'],
                'source_system' => 'finedge_legacy',
                'legacy_relationship_manager_id' => $branchResolution['legacy_relationship_manager_id'],
                'branch_resolution' => $branchResolution['resolution'],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array{branch_id: int|null, legacy_relationship_manager_id: int|null, resolution: string}  $branchResolution
     */
    private function trackBranchResolution(array &$stats, array $branchResolution): void
    {
        if ($branchResolution['branch_id']) {
            $stats['branch_assigned']++;
        }
        if (str_contains($branchResolution['resolution'], 'fallback')) {
            $stats['branch_head_office_fallback']++;
        }
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    private function resolveMigrationEmail(array $legacyUser, int $userId): string
    {
        $email = trim((string) ($legacyUser['email'] ?? ''));
        if ($email === '') {
            return 'legacy-user-'.$userId.'@migration.local';
        }

        if (! Customer::query()->where('email', $email)->exists()) {
            return $email;
        }

        return 'legacy-user-'.$userId.'@migration.local';
    }

    /**
     * @param  array<string, mixed>  $legacyUser
     */
    private function resolveMigrationPhone(array $legacyUser, int $userId): ?string
    {
        $phone = $this->phoneNormalizer->normalize($legacyUser['phone_number'] ?? null)
            ?? trim((string) ($legacyUser['phone_number'] ?? ''));

        if ($phone === '') {
            return null;
        }

        if (! Customer::query()->where('phone', $phone)->exists()) {
            return $phone;
        }

        $suffix = '-leg'.$userId;
        $candidate = strlen($phone) + strlen($suffix) <= 20
            ? $phone.$suffix
            : substr($phone, 0, max(1, 20 - strlen($suffix))).$suffix;

        return Customer::query()->where('phone', $candidate)->exists()
            ? null
            : $candidate;
    }

    /**
     * @param  array<string, mixed>  $legacyCustomer
     */
    private function stageCustomer(
        int $runId,
        int $userId,
        array $legacyCustomer,
        ?int $mappedId,
        string $status,
        ?string $productCode,
        ?string $exception = null,
    ): void {
        DB::table('migration_customers')->updateOrInsert(
            ['migration_run_id' => $runId, 'legacy_user_id' => $userId],
            [
                'legacy_customer_id' => $legacyCustomer['id'] ?? null,
                'legacy_client_id' => $legacyCustomer['client_id'] ?? null,
                'mapped_customer_id' => $mappedId,
                'target_product_code' => $productCode,
                'migration_status' => $status,
                'exception' => $exception,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
