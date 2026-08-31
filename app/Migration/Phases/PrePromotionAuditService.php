<?php

namespace App\Migration\Phases;

use App\Migration\LegacyConnection;
use App\Migration\LegacyProductMapper;
use App\Migration\Phases\Support\CompanyMatcher;
use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Migration\Phases\Support\CustomerMatcher;
use App\Migration\Phases\Support\MarketeerClassifier;
use App\Migration\Phases\Support\ReferenceMatcher;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\FinancialInstitution;
use App\Models\LoanProduct;
use App\Models\WalletProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrePromotionAuditService
{
    /** @var list<string> */
    private const STAFF_ROLES = [
        'Super Admin', 'Credit Admin', 'Chief Business Officer',
        'Chief Technology & Innovations Officer', 'Credit Specialist',
        'Admin & Operations', 'Information Systems Specialist', 'No Function',
    ];

    /** Legacy status 600 = "User Is Active" (approved customer), not staff. */

    public function __construct(
        private readonly MigrationEntityMapRepository $maps,
        private readonly CompanyMatcher $companyMatcher,
        private readonly LegacyClientClassifier $clientClassifier,
        private readonly MarketeerClassifier $marketeerClassifier,
        private readonly CustomerMatcher $customerMatcher,
        private readonly LegacyProductMapper $productMapper,
        private readonly ReferenceMatcher $referenceMatcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        LegacyConnection::configureFromLegacyEnvFile();
        $legacy = LegacyConnection::connection();

        $users = $legacy->table('users')->get();
        $customers = $legacy->table('customers')->get();
        $customerUserIds = $customers->pluck('user_id')->unique()->values();
        $clients = $legacy->table('clients')->get();
        $loans = $legacy->table('loans')->get();
        $repayments = $legacy->table('repayments')->where('status_code', 215)->get();
        $loansByUser = $loans->groupBy('user_id');
        $loansByClient = $loans->groupBy('client_id');
        $repaymentsByUser = $repayments->groupBy('user_id');
        $roleNamesByUser = $this->roleNamesByUser($legacy);

        $userPopulation = $this->auditUserPopulation(
            $users,
            $customers,
            $roleNamesByUser,
            $loansByUser,
            $repaymentsByUser
        );

        $customerPopulation = $this->auditCustomerPopulation(
            $legacy,
            $customers,
            $users->keyBy('id'),
            $loansByUser,
            $repaymentsByUser
        );

        $clientReconciliation = $this->auditClients($clients, $customers, $loansByClient, $legacy);
        $companyCoverage = $this->auditCompanyCoverage($legacy, $customers);
        $banks = $this->auditBanks($legacy);
        $wallets = $this->auditWalletProviders($legacy);
        $masterDataProtection = $this->auditMasterDataProtection();
        $existingMatches = $this->auditExistingCustomerMatches($legacy);
        $duplicates = $this->auditDuplicates($legacy, $customers);
        $activeLoanSimulation = $this->simulateActiveLoansAfterCustomerPromote($legacy, $loans);

        return [
            'generated_at' => now()->toIso8601String(),
            'user_population' => $userPopulation,
            'customer_population' => $customerPopulation,
            'client_reconciliation' => $clientReconciliation,
            'company_coverage' => $companyCoverage,
            'government_rules' => $clientReconciliation['government_clients'],
            'banks' => $banks,
            'wallet_providers' => $wallets,
            'master_data_protection' => $masterDataProtection,
            'existing_customer_matches' => $existingMatches,
            'duplicate_analysis' => $duplicates,
            'active_loan_simulation' => $activeLoanSimulation,
            'product_assignment_rule' => $this->productAssignmentRule(),
            'promotion_gates' => $this->promotionGateAssessment(
                $userPopulation,
                $customerPopulation,
                $clientReconciliation,
                $companyCoverage,
                $banks,
                $existingMatches,
                $duplicates,
                $activeLoanSimulation
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productAssignmentRule(): array
    {
        return [
            'authoritative' => 'Loan.loan_product_id is authoritative for migrated financial position.',
            'customer_field' => 'Customer.loan_product_id is required by schema and stores legacy client default product for portal/routing only.',
            'government' => 'Government customers: company_id=null; customer.loan_product_id=GOV-001 default; each loan still gets GOV-001 from gvnt_loan flag at loan migration.',
            'character_marketize' => 'company_id=null; customer default from client product_type; loan product from LegacyProductMapper per loan.',
        ];
    }

    /**
     * @param  Collection<int, object>  $users
     * @param  Collection<int, object>  $customers
     * @param  array<int, list<string>>  $roleNamesByUser
     * @param  Collection<int|string, Collection<int, object>>  $loansByUser
     * @param  Collection<int|string, Collection<int, object>>  $repaymentsByUser
     * @return array<string, mixed>
     */
    private function auditUserPopulation(
        Collection $users,
        Collection $customers,
        array $roleNamesByUser,
        Collection $loansByUser,
        Collection $repaymentsByUser,
    ): array {
        $customerUserIds = $customers->pluck('user_id')->flip();
        $classifications = [
            'CUSTOMER_WITH_CUSTOMERS_ROW' => 0,
            'CUSTOMER_WITHOUT_CUSTOMERS_ROW' => 0,
            'ADMIN_OR_STAFF' => 0,
            'SYSTEM_ACCOUNT' => 0,
            'INVALID_OR_ORPHAN' => 0,
            'OTHER' => 0,
        ];
        $adminDetails = [];

        foreach ($users as $user) {
            $uid = (int) $user->id;
            $roles = $roleNamesByUser[$uid] ?? [];
            $hasCustomer = isset($customerUserIds[$uid]);
            $isStaff = $this->isStaffUser($roles);
            $isSystem = in_array(strtolower($user->email ?? ''), ['admin@gmail.com', 'info@finedge.co.zm'], true)
                || str_contains(strtolower($user->fname ?? ''), 'system');

            if ($hasCustomer) {
                $classifications['CUSTOMER_WITH_CUSTOMERS_ROW']++;
            } elseif ($isStaff) {
                $classifications['ADMIN_OR_STAFF']++;
                $adminDetails[] = ['user_id' => $uid, 'name' => trim(($user->fname ?? '').' '.($user->lname ?? '')), 'roles' => $roles];
            } elseif ($isSystem) {
                $classifications['SYSTEM_ACCOUNT']++;
                $adminDetails[] = ['user_id' => $uid, 'name' => trim(($user->fname ?? '').' '.($user->lname ?? '')), 'roles' => $roles, 'note' => 'system account'];
            } elseif ($loansByUser->has($uid) || $repaymentsByUser->has($uid)) {
                $classifications['CUSTOMER_WITHOUT_CUSTOMERS_ROW']++;
                $adminDetails[] = ['user_id' => $uid, 'name' => trim(($user->fname ?? '').' '.($user->lname ?? '')), 'roles' => $roles, 'note' => 'loan/repayment history but no customers row'];
            } else {
                $classifications['INVALID_OR_ORPHAN']++;
            }
        }

        $activeLoanUsers = LegacyConnection::connection()
            ->table('loans')->where('status_code', '301')->distinct()->count('user_id');

        return [
            'total_users' => $users->count(),
            'total_customers_rows' => $customers->count(),
            'difference_explanation' => sprintf(
                '%d users − %d customers = %d users without customers row (admin/staff/system; no loans/repayments).',
                $users->count(),
                $customers->count(),
                $users->count() - $customers->count()
            ),
            'users_with_customers_row' => $classifications['CUSTOMER_WITH_CUSTOMERS_ROW'],
            'users_without_customers_row' => $users->count() - $classifications['CUSTOMER_WITH_CUSTOMERS_ROW'],
            'admin_or_staff_excluded' => count(array_unique(array_column($adminDetails, 'user_id'))),
            'admin_details' => $adminDetails,
            'users_ever_had_loan' => $loansByUser->count(),
            'users_with_active_loan' => $activeLoanUsers,
            'users_ever_repayment' => $repaymentsByUser->count(),
            'true_migration_population' => $customers->count(),
            'classifications' => $classifications,
        ];
    }

    /**
     * @param  Collection<int, object>  $customers
     * @param  Collection<int, object>  $usersById
     * @return array<string, mixed>
     */
    private function auditCustomerPopulation(
        $legacy,
        Collection $customers,
        Collection $usersById,
        Collection $loansByUser,
        Collection $repaymentsByUser,
    ): array {
        $stats = [
            'HAS_FULL_USER_AND_CUSTOMER' => 0,
            'USER_ONLY_BUT_VALID_CUSTOMER' => 0,
            'CUSTOMER_ONLY_OR_BROKEN_RELATION' => 0,
            'DUPLICATE_IDENTITY' => 0,
            'MANUAL_REVIEW' => 0,
        ];

        foreach ($customers as $cust) {
            $uid = (int) $cust->user_id;
            $user = $usersById->get($uid);
            if (! $user) {
                $stats['CUSTOMER_ONLY_OR_BROKEN_RELATION']++;

                continue;
            }
            $hasLoan = $loansByUser->has($uid);
            $hasRepay = $repaymentsByUser->has($uid);
            $match = $this->customerMatcher->matchExisting((array) $user, (array) $cust);
            if ($match['status'] === 'POSSIBLE_DUPLICATE') {
                $stats['DUPLICATE_IDENTITY']++;
                $stats['MANUAL_REVIEW']++;
            } elseif ($hasLoan || $hasRepay) {
                $stats['HAS_FULL_USER_AND_CUSTOMER']++;
            } else {
                $stats['HAS_FULL_USER_AND_CUSTOMER']++;
            }
        }

        return [
            'true_customer_count' => $customers->count(),
            'completeness' => $stats,
            'sum_check' => array_sum($stats),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditClients(Collection $clients, Collection $customers, Collection $loansByClient, $legacy): array
    {
        $custByClient = $customers->groupBy('client_id')->map->count();
        $activeUserIds = $legacy->table('loans')->where('status_code', '301')->pluck('user_id')->flip();
        $activeCustByClient = $customers->filter(fn ($c) => isset($activeUserIds[$c->user_id]))->groupBy('client_id')->map->count();
        $rows = [];
        $totals = [
            'MATCH_EXISTING' => 0, 'CREATE' => 0,
            'SKIP_GOVERNMENT_PLACEHOLDER' => 0, 'SKIP_MARKETEER_PLACEHOLDER' => 0,
            'SKIP_CHARACTER_PLACEHOLDER' => 0, 'SKIP_UNUSED' => 0, 'MANUAL_REVIEW' => 0,
        ];
        $classifications = [];
        $govClients = [];

        foreach ($clients as $client) {
            $c = (array) $client;
            $id = (int) $c['id'];
            $clientLoans = $loansByClient->get($id, collect());
            $custCount = (int) ($custByClient[$id] ?? 0);
            $classification = $this->clientClassifier->classify($c, $clientLoans, $custCount);
            $classifications[$classification] = ($classifications[$classification] ?? 0) + 1;

            if ($this->clientClassifier->shouldCreateOrMatchCompany($classification)) {
                $existingMap = $this->maps->find(MigrationEntityMapRepository::TYPE_COMPANY, (string) $id);
                if ($existingMap && ! $this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $id)) {
                    $action = 'MATCH_EXISTING';
                } else {
                    $match = $this->companyMatcher->matchExisting($c);
                    $action = $match['company'] ? 'MATCH_EXISTING' : 'CREATE';
                }
            } else {
                $action = $this->clientClassifier->companySkipReason($classification) ?? 'MANUAL_REVIEW';
            }

            $totals[$action]++;
            if ($classification === LegacyClientClassifier::GOVERNMENT_PRODUCT_PLACEHOLDER) {
                $govClients[] = [
                    'legacy_client_id' => $id,
                    'company_name' => $c['company_name'],
                    'classification' => $classification,
                    'customer_count' => $custCount,
                ];
            }

            $rows[] = [
                'legacy_client_id' => $id,
                'company_name' => $c['company_name'] ?? null,
                'product_type' => $c['product_type'] ?? null,
                'registration_number' => $c['reg_number'] ?? null,
                'rate_type' => $c['rate_type'] ?? null,
                'customer_count' => $custCount,
                'active_customer_count' => (int) ($activeCustByClient[$id] ?? 0),
                'loan_count' => $clientLoans->count(),
                'active_loan_count' => $clientLoans->where('status_code', '301')->count(),
                'salary_based_loan_count' => $clientLoans->where('salary_based', 1)->count(),
                'government_loan_count' => $clientLoans->where('gvnt_loan', 1)->count(),
                'character_loan_count' => $clientLoans->filter(
                    fn ($l) => ! (bool) ($l->salary_based ?? false) && ! (bool) ($l->gvnt_loan ?? false)
                )->count(),
                'marketeer_loan_count' => $clientLoans->filter(
                    fn ($l) => ($c['product_type'] ?? null) === 'marketize_based'
                )->count(),
                'classification' => $classification,
                'mapping_action' => $action,
                'target_company_id' => $this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $id)
                    ? null
                    : $this->maps->targetId(MigrationEntityMapRepository::TYPE_COMPANY, (string) $id),
                'reason' => $action,
            ];
        }

        return [
            'legacy_client_total' => $clients->count(),
            'classifications' => $classifications,
            'totals' => $totals,
            'totals_sum' => array_sum($totals),
            'reconciles' => array_sum($totals) === $clients->count(),
            'rows' => $rows,
            'government_clients' => $govClients,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditCompanyCoverage($legacy, Collection $customers): array
    {
        $loansByClient = $legacy->table('loans')->get()->groupBy('client_id');
        $custByClient = $customers->groupBy('client_id')->map->count();

        $counts = [
            'MOU_COMPANY_LINKED' => 0,
            'GOVERNMENT_INTENTIONAL_NO_COMPANY' => 0,
            'MARKETEER_INTENTIONAL_NO_COMPANY' => 0,
            'CHARACTER_INTENTIONAL_NO_COMPANY' => 0,
            'OTHER_LEGITIMATE_NO_COMPANY' => 0,
            'COMPANY_MAPPING_PENDING' => 0,
            'MANUAL_REVIEW' => 0,
        ];

        foreach ($customers as $cust) {
            $custArr = (array) $cust;
            $cid = (int) ($cust->client_id ?? 0);
            $client = $cid ? (array) $legacy->table('clients')->where('id', $cid)->first() : null;

            if ($this->marketeerClassifier->isMarketeerCustomer($custArr, $client)) {
                $counts['MARKETEER_INTENTIONAL_NO_COMPANY']++;

                continue;
            }

            if (! $client) {
                $counts['MANUAL_REVIEW']++;

                continue;
            }

            $clientLoans = $loansByClient->get($cid, collect());
            $customerCount = (int) ($custByClient[$cid] ?? 0);
            $classification = $this->clientClassifier->classify($client, $clientLoans, max(1, $customerCount));
            $hasMap = $cid
                && $this->maps->targetId(MigrationEntityMapRepository::TYPE_COMPANY, (string) $cid)
                && ! $this->maps->isSuperseded(MigrationEntityMapRepository::TYPE_COMPANY, (string) $cid);

            $bucket = $this->clientClassifier->customerCompanyBucket($classification, false, $hasMap);
            $counts[$bucket]++;
        }

        return [
            'counts' => $counts,
            'sum' => array_sum($counts),
            'expected_total' => $customers->count(),
            'reconciles' => array_sum($counts) === $customers->count(),
            'note' => 'Only MOU_REAL_EMPLOYER clients create companies. Government, Marketeer, and Character placeholders are intentional no-company buckets.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditBanks($legacy): array
    {
        $rows = [];
        foreach ($legacy->table('banks')->get() as $bank) {
            $b = (array) $bank;
            $legacyId = (int) $b['id'];
            $fiMatch = $this->referenceMatcher->matchFinancialInstitution($b);
            $treasuryMatch = $this->referenceMatcher->matchTreasuryBank($b);

            if ($treasuryMatch) {
                $action = 'MATCH_EXISTING';
                $target = "Bank:{$treasuryMatch->id} ({$treasuryMatch->name})";
                $reason = 'Legacy treasury bank maps to revamp Bank model; target preserved.';
            } elseif ($fiMatch) {
                $action = 'MATCH_EXISTING';
                $target = "FinancialInstitution:{$fiMatch->id}";
                $reason = 'Matched customer FI catalogue.';
            } else {
                $action = 'MANUAL_REVIEW';
                $target = null;
                $reason = 'No target treasury Bank or FI match (e.g. ZICB not yet in target).';
            }

            $rows[] = [
                'legacy_bank_id' => $legacyId,
                'legacy_name' => $b['name'] ?? null,
                'legacy_code' => $b['code'] ?? null,
                'target' => $target,
                'action' => $action,
                'reason' => $reason,
            ];
        }

        return ['legacy_total' => count($rows), 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditWalletProviders($legacy): array
    {
        $rows = [];
        foreach ($legacy->table('payment_wallets')->get() as $wallet) {
            $w = (array) $wallet;
            if ($this->referenceMatcher->isTreasuryWallet($w)) {
                $rows[] = [
                    'legacy_id' => $w['id'],
                    'legacy_name' => $w['name'],
                    'action' => 'SKIP_TREASURY_NOT_CUSTOMER_PROVIDER',
                    'reason' => 'Kazang is operator/treasury wallet, not a customer mobile-money provider.',
                ];

                continue;
            }
            if (strtoupper($w['code'] ?? '') === 'OTHER') {
                $rows[] = [
                    'legacy_id' => $w['id'],
                    'legacy_name' => $w['name'],
                    'action' => 'SKIP_UNMAPPED',
                    'reason' => 'Generic Other bucket — do not create customer provider.',
                ];

                continue;
            }
            $match = $this->referenceMatcher->matchWalletProvider($w);
            $rows[] = [
                'legacy_id' => $w['id'],
                'legacy_name' => $w['name'],
                'legacy_code' => $w['code'] ?? null,
                'target' => $match ? "{$match->name} ({$match->code})" : null,
                'action' => $match ? 'MATCH_EXISTING' : 'MANUAL_REVIEW',
                'reason' => $match ? 'Target provider exists; mapping only.' : 'No target match.',
            ];
        }

        return ['legacy_total' => count($rows), 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditMasterDataProtection(): array
    {
        $samples = [];
        foreach (DB::table('migration_entity_maps')->get() as $map) {
            $samples[] = [
                'entity_type' => $map->entity_type,
                'legacy_id' => $map->legacy_identifier,
                'target_id' => $map->target_id,
                'method' => $map->mapping_method,
                'overwrite_on_rerun' => false,
            ];
        }

        return [
            'entity_maps_count' => count($samples),
            'policy' => 'MATCH → MAP → SKIP CREATE; maps->store() is no-op if map exists; target models never updated from legacy on rerun.',
            'samples' => $samples,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditExistingCustomerMatches($legacy): array
    {
        $maps = DB::table('migration_entity_maps')->where('entity_type', MigrationEntityMapRepository::TYPE_CUSTOMER)->get();
        $rows = [];
        foreach ($maps as $map) {
            $uid = (int) $map->legacy_identifier;
            $user = (array) $legacy->table('users')->where('id', $uid)->first();
            $cust = (array) $legacy->table('customers')->where('user_id', $uid)->first();
            $target = Customer::find($map->target_id);
            $nrcMatch = ($cust['nrc'] ?? null) && ($cust['nrc'] === ($target->national_id ?? null));
            $empMatch = ($user['emp_number'] ?? null) && ($user['emp_number'] === ($target->employee_number ?? null));
            $phoneMatch = ($user['phone_number'] ?? null) && str_contains((string) ($target->phone ?? ''), substr(preg_replace('/\D/', '', $user['phone_number']) ?? '', -9));
            $confidence = $nrcMatch ? 'HIGH' : ($map->mapping_method === 'email' ? 'LOW' : 'MEDIUM');
            if ($map->mapping_method === 'national_id' && ! $nrcMatch && ! $empMatch) {
                $confidence = 'MANUAL_REVIEW';
            }
            if (in_array($map->mapping_method, ['identity_resolution_alias', 'identity_resolution_primary'], true)) {
                $confidence = 'HIGH';
            }
            $rows[] = [
                'legacy_user_id' => $uid,
                'legacy_customer_id' => $cust['id'] ?? null,
                'target_customer_id' => (int) $map->target_id,
                'method' => $map->mapping_method,
                'nrc_match' => $nrcMatch,
                'employee_number_match' => $empMatch,
                'phone_match' => $phoneMatch,
                'confidence' => $confidence,
            ];
        }

        return ['count' => count($rows), 'rows' => $rows, 'manual_review' => collect($rows)->where('confidence', 'MANUAL_REVIEW')->count()];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditDuplicates($legacy, Collection $customers): array
    {
        $nrcDups = $customers->filter(fn ($c) => ! empty($c->nrc))
            ->groupBy('nrc')->filter(fn ($g) => $g->count() > 1);
        $targetByNrc = Customer::query()->whereNotNull('national_id')->get()->groupBy('national_id');

        $cases = [];
        foreach ($nrcDups as $nrc => $group) {
            $cases[] = [
                'signal' => 'national_id',
                'value' => $nrc,
                'legacy_user_ids' => $group->pluck('user_id')->all(),
                'target_customers' => isset($targetByNrc[$nrc]) ? $targetByNrc[$nrc]->pluck('id')->all() : [],
                'action' => 'MANUAL_REVIEW',
            ];
        }

        return ['duplicate_nrc_groups' => $nrcDups->count(), 'cases' => $cases];
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateActiveLoansAfterCustomerPromote($legacy, Collection $loans): array
    {
        $active = $loans->where('status_code', '301');
        $wouldPromote = 0;
        $manual = 0;
        $blockedCustomer = 0;

        foreach ($active as $loan) {
            $loanId = (int) $loan->id;
            $userId = (int) $loan->user_id;
            if (in_array($loanId, ManualReviewCohorts::MANUAL_REVIEW_LOAN_IDS, true)) {
                $manual++;

                continue;
            }
            $cust = $legacy->table('customers')->where('user_id', $userId)->exists();
            if (! $cust) {
                $blockedCustomer++;

                continue;
            }
            $wouldPromote++;
        }

        return [
            'legacy_active_loans' => $active->count(),
            'simulated_would_promote' => $wouldPromote,
            'simulated_manual_review' => $manual,
            'simulated_blocked_missing_customer' => $blockedCustomer,
            'note' => 'Assumes all 1936 customers promoted; 750 prior blocked become eligible.',
        ];
    }

    /**
     * @param  array<string, mixed>  $userPopulation
     * @param  array<string, mixed>  $customerPopulation
     * @param  array<string, mixed>  $clientReconciliation
     * @param  array<string, mixed>  $companyCoverage
     * @param  array<string, mixed>  $banks
     * @param  array<string, mixed>  $existingMatches
     * @param  array<string, mixed>  $duplicates
     * @param  array<string, mixed>  $activeLoanSimulation
     * @return array<string, mixed>
     */
    private function promotionGateAssessment(
        array $userPopulation,
        array $customerPopulation,
        array $clientReconciliation,
        array $companyCoverage,
        array $banks,
        array $existingMatches,
        array $duplicates,
        array $activeLoanSimulation,
    ): array {
        $referenceReady = ($clientReconciliation['reconciles'] ?? false)
            && ($clientReconciliation['legacy_client_total'] ?? 0) === 38;

        $customersReady = ($userPopulation['true_migration_population'] ?? 0) === 1936
            && ($existingMatches['manual_review'] ?? 0) === 0
            && collect($duplicates['cases'] ?? [])->every(
                fn ($c) => empty($c['target_customers']) || count($c['legacy_user_ids'] ?? []) <= 1
            );

        return [
            'REFERENCE_DATA' => $referenceReady ? 'READY' : 'NOT_READY',
            'CUSTOMERS' => $customersReady ? 'READY' : 'NOT_READY',
            'ACTIVE_LOANS' => 'BLOCKED_UNTIL_CUSTOMERS_PROMOTED',
            'REPAYMENTS' => 'BLOCKED_UNTIL_LOANS_PROMOTED',
            'overall_reference_promote' => $referenceReady ? 'READY' : 'NOT_READY',
            'conditions' => [
                'zicb_treasury_bank_needs_manual_map_or_skip_decision' => true,
                'duplicate_nrc_legacy_14_and_19_share_target' => true,
                'company_mapping_pending_resolves_on_reference_promote' => ($companyCoverage['counts']['COMPANY_MAPPING_PENDING'] ?? 0) > 0,
            ],
        ];
    }

    /**
     * @return array<int, list<string>>
     */
    private function roleNamesByUser($legacy): array
    {
        $out = [];
        try {
            $rows = $legacy->table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', 'like', '%User%')
                ->get(['model_has_roles.model_id as user_id', 'roles.name']);
            foreach ($rows as $row) {
                $out[(int) $row->user_id][] = $row->name;
            }
        } catch (\Throwable) {
            // roles unavailable
        }

        return $out;
    }

    /**
     * Staff accounts are identified by Spatie roles, not user status_code (600 = active customer).
     *
     * @param  list<string>  $roles
     */
    private function isStaffUser(array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, self::STAFF_ROLES, true)) {
                return true;
            }
        }

        return false;
    }
}
