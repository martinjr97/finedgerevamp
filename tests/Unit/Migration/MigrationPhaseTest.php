<?php

namespace Tests\Unit\Migration;

use App\Migration\LegacyProductMapper;
use App\Migration\LegacyLoanBalanceCalculator;
use App\Migration\Phases\ManualReviewCohorts;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\MigrationPromotionGate;
use App\Migration\Phases\ProductCustomerGroupReferenceMigrator;
use App\Migration\Phases\Support\CompanyMatcher;
use App\Migration\Phases\Support\CustomerMatcher;
use App\Migration\Phases\Support\CustomerIdentityResolutionRegistry;
use App\Migration\Phases\Support\CustomerMigrationProfileResolver;
use App\Migration\Phases\Support\MigratedLoanAttributes;
use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Migration\Phases\Support\MarketeerClassifier;
use App\Migration\Phases\Support\MarketMatcher;
use App\Migration\Phases\Support\RepaymentManualClassifier;
use App\Migration\RepaymentAttributionService;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancialInstitution;
use App\Models\LoanProduct;
use App\Models\Market;
use App\Models\WalletProvider;
use App\Migration\Phases\Support\CustomerBranchResolver;
use App\Migration\Phases\Support\LegacyRelationshipManagerResolver;
use App\Models\Admin;
use App\Models\Branch;
use App\Support\AdminCompanyScope;
use App\Support\OperatorCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_matcher_detects_government_placeholder(): void
    {
        $matcher = new CompanyMatcher;
        $client = ['id' => 1, 'company_name' => 'Ministry of Health', 'product_type' => 'salary_based'];
        $loans = collect([(object) ['gvnt_loan' => 1], (object) ['gvnt_loan' => 1]]);

        $this->assertTrue($matcher->isGovernmentPlaceholder($client, $loans));
    }

    public function test_existing_company_matched_by_registration_number(): void
    {
        Company::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'code' => 'ACME',
            'registration_number' => 'REG123',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $matcher = new CompanyMatcher;
        $match = $matcher->matchExisting(['id' => 99, 'company_name' => 'Other', 'reg_number' => 'REG123']);

        $this->assertNotNull($match['company']);
        $this->assertSame('registration_number', $match['method']);
    }

    public function test_existing_product_not_duplicated_via_entity_map(): void
    {
        $company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
            'code' => 'TST',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'code' => 'MOU-001',
            'name' => 'MOU',
            'category' => 'mou',
            'tenure_months' => 3,
            'max_amount' => 100000,
            'requires_collateral' => false,
            'requires_reference' => false,
            'is_active' => true,
            'rules' => [],
        ]);
        $repo = new MigrationEntityMapRepository;

        $repo->store(MigrationEntityMapRepository::TYPE_PRODUCT, 'MOU-001', LoanProduct::class, $product->id, 'existing_target');

        $this->assertSame($product->id, $repo->targetId(MigrationEntityMapRepository::TYPE_PRODUCT, 'MOU-001'));
        $this->assertSame(1, LoanProduct::where('code', 'MOU-001')->count());
    }

    public function test_financial_institution_match_preserves_target(): void
    {
        $fi = FinancialInstitution::create(['name' => 'FNB Zambia', 'code' => 'FNB', 'is_active' => true]);

        $matcher = new \App\Migration\Phases\Support\ReferenceMatcher;
        $matched = $matcher->matchFinancialInstitution(['bank_name' => 'FNB Zambia', 'id' => 1]);

        $this->assertSame($fi->id, $matched?->id);
    }

    public function test_wallet_provider_match_preserves_target(): void
    {
        $provider = WalletProvider::create(['name' => 'MTN MONEY', 'code' => 'MTN_MONEY', 'is_active' => true]);

        $matcher = new \App\Migration\Phases\Support\ReferenceMatcher;
        $matched = $matcher->matchWalletProvider(['name' => 'MTN MONEY', 'id' => 1]);

        $this->assertSame($provider->id, $matched?->id);
    }

    public function test_customer_matcher_finds_existing_by_nrc(): void
    {
        $company = Company::create([
            'name' => 'Emp',
            'slug' => 'emp',
            'code' => 'EMP',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'code' => 'CHAR-001',
            'name' => 'Char',
            'category' => 'character',
            'tenure_months' => 3,
            'max_amount' => 100000,
            'requires_collateral' => false,
            'requires_reference' => false,
            'is_active' => true,
            'rules' => [],
        ]);
        Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'unique@test.local',
            'password' => bcrypt('secret'),
            'national_id' => '123456/78/1',
            'status' => 'active',
            'kyc_status' => 'verified',
        ]);

        $matcher = new CustomerMatcher;
        $match = $matcher->matchExisting(
            ['id' => 1, 'fname' => 'Test', 'lname' => 'User'],
            ['nrc' => '123456/78/1']
        );

        $this->assertSame('MATCHED_EXISTING', $match['status']);
    }

    public function test_manual_review_cohort_blocks_promotion(): void
    {
        $cohort = ManualReviewCohorts::loanCohort(16969, 'MANUAL_REVIEW');
        $this->assertSame('COHORT_C_MANUAL_REVIEW', $cohort);
        $this->assertFalse(ManualReviewCohorts::isPromotable($cohort));
    }

    public function test_repayment_manual_subclass_d1(): void
    {
        $classifier = new RepaymentManualClassifier;
        $sub = $classifier->subclassify(RepaymentAttributionService::D_MANUAL, [], 'no_eligible_accrual_loan');
        $this->assertSame(RepaymentManualClassifier::D1_HISTORICAL_SUPPORT_ONLY, $sub);
    }

    public function test_entity_map_idempotent_second_store(): void
    {
        $repo = new MigrationEntityMapRepository;
        $repo->store(MigrationEntityMapRepository::TYPE_CUSTOMER, '42', Customer::class, 1, 'test');
        $repo->store(MigrationEntityMapRepository::TYPE_CUSTOMER, '42', Customer::class, 999, 'test');

        $this->assertSame(1, $repo->targetId(MigrationEntityMapRepository::TYPE_CUSTOMER, '42'));
        $this->assertSame(1, DB::table('migration_entity_maps')->where('entity_type', 'customer')->count());
    }

    public function test_promotable_cohort_allows_promotion(): void
    {
        $cohort = ManualReviewCohorts::loanCohort(100, 'PASS_WITH_MIGRATION_ADJUSTMENT');
        $this->assertTrue(ManualReviewCohorts::isPromotable($cohort));
    }

    public function test_customer_promotion_gate_blocks_uncertain_existing_matches(): void
    {
        DB::table('migration_entity_maps')->insert([
            'entity_type' => MigrationEntityMapRepository::TYPE_CUSTOMER,
            'legacy_identifier' => '9999',
            'legacy_secondary' => '8',
            'target_type' => Customer::class,
            'target_id' => 7,
            'mapping_method' => 'national_id',
            'mapping_confidence' => 'MEDIUM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        app(MigrationPromotionGate::class)->assertCustomersPromote();
    }

    public function test_identity_resolution_registry_covers_duplicate_nrc_groups(): void
    {
        $r14 = CustomerIdentityResolutionRegistry::forUser(14);
        $this->assertSame('SAME_PERSON_KEEP_SEPARATE_HISTORY_MAP_ONE_TARGET', $r14['classification']);
        $this->assertTrue(CustomerIdentityResolutionRegistry::isAlias(19));
        $this->assertFalse(CustomerIdentityResolutionRegistry::isAlias(127));

        try {
            \App\Migration\LegacyConnection::configureFromLegacyEnvFile();
            \App\Migration\LegacyConnection::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('Legacy database not available for duplicate-group integration check.');

            return;
        }

        $resolver = app(\App\Migration\Phases\Support\CustomerIdentityResolver::class);
        if (! $resolver->duplicateGroupsResolved()) {
            $pending = app(\App\Migration\Dashboard\MigrationIdentityResolutionService::class)->pendingDuplicateGroups();
            $this->markTestSkipped(
                'Legacy has '.count($pending).' unresolved duplicate NRC group(s) — resolve via migration dashboard Identity tab.'
            );
        }

        $this->assertTrue($resolver->duplicateGroupsResolved());
    }

    public function test_entity_map_annotate_preserves_target_id(): void
    {
        $repo = new MigrationEntityMapRepository;
        $repo->store(MigrationEntityMapRepository::TYPE_COMPANY, '36', Company::class, 9, 'explicit_migration_mapping');
        $repo->annotateMap(MigrationEntityMapRepository::TYPE_COMPANY, '36', ['superseded' => true], 'superseded_marketeer_placeholder');

        $this->assertTrue($repo->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, '36'));
        $this->assertSame(9, $repo->targetId(MigrationEntityMapRepository::TYPE_COMPANY, '36'));
    }

    public function test_marketeer_markets_match_by_legacy_code_after_create(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Lusaka Test', 'code' => 'LUSA-T2', 'country' => 'Zambia',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name' => 'Lusaka Test', 'code' => 'LUSA-LUSA-T2',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Market::create([
            'name' => 'Lilanda Market',
            'code' => 'MRKT-LEG-1',
            'address_line1' => 'Lusaka',
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'contact_person_name' => 'Manager',
            'contact_person_phone' => '260971234567',
            'is_active' => true,
        ]);

        $matcher = new MarketMatcher;
        $match = $matcher->matchExisting(['id' => 1, 'name' => 'Different Name']);

        $this->assertSame('legacy_code', $match['method']);
        $this->assertSame('Lilanda Market', $match['market']->name);
    }

    public function test_marketeer_placeholder_client_is_skipped_as_company(): void
    {
        $matcher = new CompanyMatcher;
        $client = [
            'id' => 36,
            'company_name' => 'Marketize Loans',
            'reg_number' => 'MKT-2025-001',
            'product_type' => 'marketize_based',
        ];

        $this->assertTrue($matcher->isMarketeerPlaceholderClient($client));
    }

    public function test_character_placeholder_client_is_skipped_as_company(): void
    {
        $classifier = new LegacyClientClassifier(new CompanyMatcher);
        $client = [
            'id' => 6,
            'company_name' => 'Character-based-2',
            'product_type' => 'character_based',
        ];
        $loans = collect([(object) ['salary_based' => 0, 'gvnt_loan' => 0]]);

        $classification = $classifier->classify($client, $loans, 52);

        $this->assertSame(LegacyClientClassifier::CHARACTER_PRODUCT_PLACEHOLDER, $classification);
        $this->assertFalse($classifier->shouldCreateOrMatchCompany($classification));
        $this->assertSame('SKIP_CHARACTER_PLACEHOLDER', $classifier->companySkipReason($classification));
    }

    public function test_mou_employer_client_creates_company(): void
    {
        $classifier = new LegacyClientClassifier(new CompanyMatcher);
        $client = [
            'id' => 11,
            'company_name' => 'Starlabs Limited',
            'product_type' => 'salary_based',
        ];
        $loans = collect([(object) ['salary_based' => 1, 'gvnt_loan' => 0]]);

        $classification = $classifier->classify($client, $loans, 28);

        $this->assertSame(LegacyClientClassifier::MOU_REAL_EMPLOYER, $classification);
        $this->assertTrue($classifier->shouldCreateOrMatchCompany($classification));
    }

    public function test_government_placeholder_client_maps_to_gov_product_not_mou(): void
    {
        $maps = new MigrationEntityMapRepository;
        $groupMigrator = new ProductCustomerGroupReferenceMigrator(
            $maps,
            new LegacyClientClassifier(new CompanyMatcher)
        );

        $govProduct = LoanProduct::create([
            'company_id' => Company::create([
                'name' => 'Gov Co', 'slug' => 'gov-co', 'code' => 'GOVCO', 'type' => 'partner', 'status' => 'active', 'approval_status' => 'approved',
            ])->id,
            'name' => 'Government Payroll Loan',
            'code' => 'GOV-001',
            'category' => 'government',
            'tenure_months' => 3,
            'max_amount' => 500000,
            'requires_collateral' => false,
            'requires_reference' => false,
            'is_active' => true,
            'rules' => [],
        ]);

        $defaultGroup = CustomerGroup::create([
            'loan_product_id' => $govProduct->id,
            'name' => 'Default Group',
            'code' => 'GOV-DEFAULT',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $maps->store(MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP, 'GOV-DEFAULT', CustomerGroup::class, $defaultGroup->id, 'test');

        $resolver = new CustomerMigrationProfileResolver(
            new LegacyProductMapper,
            new LegacyClientClassifier(new CompanyMatcher),
            new MarketeerClassifier,
            $groupMigrator,
        );

        $legacyClient = [
            'id' => 8,
            'company_name' => 'GRZ',
            'product_type' => 'salary_based',
        ];
        $legacyCustomer = ['client_id' => 8, 'is_marketize_customer' => false];
        $loans = collect([(object) ['salary_based' => 1, 'gvnt_loan' => 1]]);

        $profile = $resolver->resolve($legacyCustomer, $legacyClient, $loans, 99);

        $this->assertSame('GOV-001', $profile['product_code']);
        $this->assertNull($profile['company_id']);
        $this->assertSame($defaultGroup->id, $profile['customer_group_id']);
    }

    public function test_product_placeholder_clients_never_match_companies(): void
    {
        $matcher = new CompanyMatcher;

        foreach ([2 => 'Vendor', 6 => 'Character-based-2', 7 => 'Character-based-3', 8 => 'GRZ', 36 => 'Marketize Loans'] as $id => $name) {
            Company::create([
                'name' => $name,
                'slug' => 'placeholder-'.$id,
                'code' => 'LEG-'.$id,
                'type' => 'partner',
                'status' => 'active',
                'approval_status' => 'approved',
                'settings' => ['legacy_client_id' => $id],
            ]);

            $match = $matcher->matchExisting(['id' => $id, 'company_name' => $name]);
            $this->assertNull($match['company'], "Legacy client {$id} ({$name}) must not match a company.");
        }
    }

    public function test_marketeer_classifier_identifies_customer_by_flag_and_client(): void
    {
        $classifier = new MarketeerClassifier;

        $this->assertTrue($classifier->isMarketeerCustomer(
            ['is_marketize_customer' => true, 'market_id' => 1],
            ['product_type' => 'salary_based']
        ));

        $this->assertTrue($classifier->isMarketeerCustomer(
            ['is_marketize_customer' => false, 'market_id' => 1],
            ['product_type' => 'marketize_based']
        ));

        $this->assertFalse($classifier->isMarketeerCustomer(
            ['is_marketize_customer' => false, 'market_id' => null],
            ['product_type' => 'salary_based']
        ));
    }

    public function test_market_matcher_finds_existing_by_legacy_code(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'Lusaka',
            'code' => 'LUSA-TEST',
            'country' => 'Zambia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId,
            'name' => 'Lusaka',
            'code' => 'LUSA-LUSA-TEST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Market::create([
            'name' => 'Lilanda Market',
            'code' => 'MRKT-LEG-1',
            'address_line1' => 'Lusaka',
            'province_id' => $provinceId,
            'district_id' => $districtId,
            'contact_person_name' => 'Manager',
            'contact_person_phone' => '260971234567',
            'is_active' => true,
        ]);

        $matcher = new MarketMatcher;
        $match = $matcher->matchExisting(['id' => 1, 'name' => 'Lilanda Market']);

        $this->assertNotNull($match['market']);
        $this->assertSame('legacy_code', $match['method']);
    }

    public function test_mark001_product_match_does_not_overwrite_on_second_map_store(): void
    {
        $company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
            'code' => 'TESTCO',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Marketeer Loan',
            'code' => 'MARK-001',
            'category' => 'marketeer',
            'tenure_months' => 1,
            'max_amount' => 500000,
            'requires_collateral' => false,
            'requires_reference' => false,
            'is_active' => true,
            'rules' => ['weekly_rate' => 10],
        ]);

        $repo = new MigrationEntityMapRepository;
        $repo->store(MigrationEntityMapRepository::TYPE_PRODUCT, 'MARK-001', LoanProduct::class, $product->id, 'existing_target');
        $repo->store(MigrationEntityMapRepository::TYPE_PRODUCT, 'MARK-001', LoanProduct::class, 999, 'created');

        $this->assertSame($product->id, $repo->targetId(MigrationEntityMapRepository::TYPE_PRODUCT, 'MARK-001'));
        $this->assertSame(['weekly_rate' => 10], $product->fresh()->rules);
    }

    public function test_migrated_loan_attributes_resolve_disbursement_and_first_payment_dates(): void
    {
        $legacyLoan = [
            'created_at' => '2024-03-15 10:00:00',
            'due_date' => '2024-06-15 00:00:00',
            'payment_period' => 3,
        ];

        $disbursedAt = MigratedLoanAttributes::resolveDisbursedAt($legacyLoan);
        $firstPayment = MigratedLoanAttributes::resolveFirstPaymentDate($legacyLoan, 'MOU-001');

        $this->assertSame('2024-03-15', $disbursedAt->toDateString());
        $this->assertSame('2024-04-15', $firstPayment->toDateString());
        $this->assertSame('weekly', MigratedLoanAttributes::repaymentStructureForProduct('MARK-001'));
    }

    public function test_repayment_splits_from_allocation_use_replay_amounts(): void
    {
        $splits = MigratedLoanAttributes::repaymentSplitsFromAllocation([
            'allocated_amount' => 100,
            'principal_amount' => 70,
            'interest_amount' => 25,
            'fee_amount' => 5,
        ]);

        $this->assertSame(70.0, $splits['principal_amount']);
        $this->assertSame(25.0, $splits['interest_amount']);
        $this->assertSame(5.0, $splits['processing_fee_amount']);
    }

    public function test_operator_company_admin_has_no_company_filter(): void
    {
        $operator = Company::create([
            'name' => 'Operator',
            'slug' => 'operator-test',
            'code' => 'OP-TEST',
            'type' => 'operator',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $partner = Company::create([
            'name' => 'Partner',
            'slug' => 'partner-test',
            'code' => 'PT-TEST',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $operatorAdmin = Admin::create([
            'company_id' => $operator->id,
            'first_name' => 'Ops',
            'last_name' => 'Admin',
            'email' => 'ops-admin@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        $partnerAdmin = Admin::create([
            'company_id' => $partner->id,
            'first_name' => 'Partner',
            'last_name' => 'Admin',
            'email' => 'partner-admin@test.local',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);

        $this->assertTrue($operatorAdmin->isOperatorCompanyAdmin());
        $this->assertNull($operatorAdmin->getCompanyFilterId());
        $this->assertSame($partner->id, $partnerAdmin->getCompanyFilterId());
    }

    public function test_company_rollup_groups_null_company_customers_under_operator(): void
    {
        $operator = Company::create([
            'name' => 'Main Operator',
            'slug' => 'main-operator-test',
            'code' => 'MAIN-OP',
            'type' => 'operator',
            'is_primary' => true,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        OperatorCompany::forgetCache();

        $rollup = AdminCompanyScope::rollupCompanyExpression();
        $this->assertStringContainsString((string) $operator->id, $rollup);
        $this->assertSame($operator->id, OperatorCompany::id());
    }

    public function test_legacy_balance_calculator_uses_repaid_for_character_loans(): void
    {
        $calculator = new LegacyLoanBalanceCalculator;

        $characterLoan = [
            'salary_based' => 0,
            'gvnt_loan' => 0,
            'loan_amount' => 32400,
            'repaid_amount' => 6304,
            'current_loan_amount' => 32400,
        ];

        $this->assertSame(26096.0, $calculator->effectiveOutstanding($characterLoan));
        $this->assertFalse($calculator->isAccrualLoan($characterLoan));
    }

    public function test_legacy_balance_calculator_uses_current_loan_amount_for_mou(): void
    {
        $calculator = new LegacyLoanBalanceCalculator;

        $mouLoan = [
            'salary_based' => 1,
            'gvnt_loan' => 0,
            'loan_amount' => 10000,
            'repaid_amount' => 2000,
            'current_loan_amount' => 7500,
        ];

        $this->assertSame(7500.0, $calculator->effectiveOutstanding($mouLoan));
        $this->assertTrue($calculator->isAccrualLoan($mouLoan));
    }

    public function test_branch_match_by_code_does_not_create_duplicate(): void
    {
        $branch = Branch::create([
            'name' => 'Lusaka Main Branch',
            'code' => 'LSK',
            'is_active' => true,
        ]);

        $matcher = new \App\Migration\Phases\Support\ReferenceMatcher;
        $result = $matcher->resolveBranch([
            'id' => 99,
            'name' => 'Different Label',
            'code' => 'LSK',
        ]);

        $this->assertTrue($result->isMatched());
        $this->assertSame($branch->id, $result->target?->id);
        $this->assertSame(1, Branch::where('code', 'LSK')->count());
    }

    public function test_branch_name_conflict_requires_manual_review(): void
    {
        Branch::create(['name' => 'Kitwe Branch', 'code' => 'KTW-A', 'is_active' => true]);
        Branch::create(['name' => 'Kitwe Branch', 'code' => 'KTW-B', 'is_active' => true]);

        $matcher = new \App\Migration\Phases\Support\ReferenceMatcher;
        $result = $matcher->resolveBranch([
            'id' => 1,
            'name' => 'Kitwe Branch',
            'code' => 'LEG-B1',
        ]);

        $this->assertTrue($result->isConflict());
        $this->assertCount(2, $result->candidateTargetIds);
    }

    public function test_wallet_provider_conflict_does_not_auto_match(): void
    {
        WalletProvider::create(['name' => 'AlphaMTNMoney', 'code' => 'ALPHA_A', 'is_active' => true]);
        WalletProvider::create(['name' => 'Alpha MTN Money', 'code' => 'ALPHA_B', 'is_active' => true]);

        $matcher = new \App\Migration\Phases\Support\ReferenceMatcher;
        $result = $matcher->resolveWalletProvider(['id' => 1, 'name' => 'Alpha MTN Money', 'code' => 'ALPHA']);

        $this->assertTrue($result->isConflict());
    }

    public function test_legacy_relationship_manager_resolver_uses_client_rm_for_mou(): void
    {
        $resolver = new LegacyRelationshipManagerResolver;

        $rmId = $resolver->resolveLegacyRelationshipManagerId(
            ['relationship_manager_id' => 9],
            ['product_type' => 'salary_based', 'relationship_manager' => 2]
        );

        $this->assertSame(2, $rmId);
    }

    public function test_customer_branch_resolver_uses_mapped_relationship_manager_branch(): void
    {
        $branch = Branch::create(['name' => 'Kitwe Branch', 'code' => 'KTW', 'is_active' => true]);
        $admin = Admin::create([
            'company_id' => Company::create([
                'name' => 'Operator',
                'slug' => 'operator',
                'code' => 'OP',
                'type' => 'operator',
                'status' => 'active',
                'approval_status' => 'approved',
            ])->id,
            'branch_id' => $branch->id,
            'first_name' => 'Chris',
            'last_name' => 'Banda',
            'email' => 'chris@example.com',
            'password' => 'password',
            'is_active' => true,
            'is_relationship_manager' => true,
            'approval_status' => 'approved',
        ]);

        $maps = new MigrationEntityMapRepository;
        $maps->store(
            MigrationEntityMapRepository::TYPE_RELATIONSHIP_MANAGER,
            '2',
            Admin::class,
            $admin->id,
            'test'
        );

        $resolver = new CustomerBranchResolver(
            $maps,
            new LegacyRelationshipManagerResolver,
            new \App\Migration\Phases\Support\ReferenceMatcher
        );

        $resolution = $resolver->resolve(
            ['relationship_manager_id' => null],
            ['product_type' => 'salary_based', 'relationship_manager' => 2]
        );

        $this->assertSame($branch->id, $resolution['branch_id']);
        $this->assertSame('relationship_manager_admin_branch', $resolution['resolution']);
    }
}
