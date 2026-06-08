<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\District;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Models\LoanRepayment;
use App\Models\Market;
use App\Models\MarketeerCustomerDetail;
use App\Models\Province;
use App\Models\Repayment;
use App\Models\Sector;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as FakerFactory;

class SampleDataGenerator
{
    /**
     * Create sample records across the new modules.
     *
     * Call via `\App\Support\SampleDataGenerator::run();` (tinker, route, etc.).
     * This is intentionally lightweight so it can be deleted after development.
     *
     * @param callable|null $progressCallback Optional callback for progress updates: function(string $message, string $type = 'info')
     * @return array<string, int> counts of created/updated records
     */
    public static function run(?callable $progressCallback = null): array
    {
        $log = function (string $message, string $type = 'info') use ($progressCallback) {
            if ($progressCallback) {
                $progressCallback($message, $type);
            }
        };

        return DB::transaction(function () use ($log) {
            $faker = FakerFactory::create('en_ZM');
            $counts = [
                'sectors' => 0,
                'provinces' => 0,
                'districts' => 0,
                'loan_products' => 0,
                'loan_rate_types' => 0,
                'loan_rates' => 0,
                'companies' => 0,
                'markets' => 0,
                'customer_groups' => 0,
                'customers' => 0,
                'admins' => 0,
                'marketeer_customer_details' => 0,
                'loans' => 0,
                'repayments' => 0,
            ];

            // --- Sectors ---
            $log('Creating sectors...', 'info');
            $sectorMap = collect([
                ['code' => 'FIN', 'name' => 'Financial Services'],
                ['code' => 'EDU', 'name' => 'Education'],
                ['code' => 'GOV', 'name' => 'Government'],
                ['code' => 'HEA', 'name' => 'Healthcare'],
                ['code' => 'MAN', 'name' => 'Manufacturing'],
                ['code' => 'AGR', 'name' => 'Agriculture'],
                ['code' => 'ENG', 'name' => 'Engineering'],
                ['code' => 'RET', 'name' => 'Retail'],
                ['code' => 'HOS', 'name' => 'Hospitality'],
                ['code' => 'ICT', 'name' => 'Technology & IT'],
                ['code' => 'LOG', 'name' => 'Logistics & Transport'],
            ])->mapWithKeys(function (array $sector) use (&$counts) {
                $record = Sector::firstOrCreate(
                    ['code' => $sector['code']],
                    [
                        'name' => $sector['name'],
                        'description' => $sector['name'].' sector',
                        'is_active' => true,
                    ]
                );

                $counts['sectors'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$sector['code'] => $record];
            });
            $log("Created/updated {$counts['sectors']} sectors", 'info');

            // --- Provinces & Districts ---
            $log('Creating provinces and districts...', 'info');
            // Use standard province codes from ProvinceSeeder to avoid duplicates
            $provinceMap = collect([
                ['code' => 'LUSA', 'name' => 'Lusaka', 'country' => 'Zambia'],
                ['code' => 'COPP', 'name' => 'Copperbelt', 'country' => 'Zambia'],
            ])->mapWithKeys(function (array $province) use (&$counts) {
                $record = Province::firstOrCreate(
                    ['code' => $province['code']],
                    [
                        'name' => $province['name'],
                        'country' => $province['country'],
                        'is_active' => true,
                    ]
                );

                $counts['provinces'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$province['code'] => $record];
            });

            $districtData = [
                ['code' => 'LUS-CEN', 'name' => 'Lusaka Central', 'province' => 'LUSA'],
                ['code' => 'NDL', 'name' => 'Ndola', 'province' => 'COPP'],
            ];

            $districtMap = collect($districtData)->mapWithKeys(function (array $district) use ($provinceMap, &$counts) {
                $record = District::firstOrCreate(
                    ['code' => $district['code']],
                    [
                        'name' => $district['name'],
                        'province_id' => $provinceMap[$district['province']]->id,
                        'is_active' => true,
                    ]
                );

                $counts['districts'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$district['code'] => $record];
            });

            // --- Loan Products ---
            $log('Creating loan products...', 'info');
            // Note: Marketeer Loan (MARK-001) is created by LoanProductSeeder, not here
            $loanProducts = collect([
                ['code' => 'MOU-001', 'name' => 'MOU Payroll Loans', 'category' => 'mou'],
                ['code' => 'CHAR-001', 'name' => 'Character Based Loans', 'category' => 'character'],
                ['code' => 'COLL-001', 'name' => 'Collateral Loans', 'category' => 'collateral'],
            ])->mapWithKeys(function (array $product) use (&$counts) {
                $record = LoanProduct::firstOrCreate(
                    ['code' => $product['code']],
                    [
                        'name' => $product['name'],
                        'category' => $product['category'],
                        'description' => $product['name'].' sample description',
                        'tenure_months' => 12,
                        'max_amount' => 250000,
                        'requires_collateral' => $product['category'] === 'collateral',
                        'requires_reference' => $product['category'] !== 'collateral',
                        'is_active' => true,
                    ]
                );

                $counts['loan_products'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$product['code'] => $record];
            });

            // Fetch Marketeer Loan (MARK-001) from database as it's created by LoanProductSeeder
            $marketeerProduct = LoanProduct::where('code', 'MARK-001')->first();
            if ($marketeerProduct) {
                $loanProducts['MARK-001'] = $marketeerProduct;
            }

            // --- Loan Rate Types + Rates ---
            $log('Creating loan rate types and rates...', 'info');
            $rateTypes = collect([
                [
                    'code' => 'MOU-STD',
                    'name' => 'MOU Standard Rate',
                    'product_code' => 'MOU-001',
                    'accrual_period' => 'daily',
                ],
                [
                    'code' => 'MRKT-WKLY',
                    'name' => 'Marketeer Weekly Accrual',
                    'product_code' => 'MARK-001',
                    'accrual_period' => 'weekly',
                ],
                [
                    'code' => 'CHAR-FLEX',
                    'name' => 'Character Flex Rate',
                    'product_code' => 'CHAR-001',
                    'accrual_period' => 'daily',
                ],
                [
                    'code' => 'COLL-SEC',
                    'name' => 'Collateral Secure Rate',
                    'product_code' => 'COLL-001',
                    'accrual_period' => 'daily',
                ],
            ])->mapWithKeys(function (array $type) use (&$counts, $loanProducts) {
                $record = LoanRateType::firstOrCreate(
                    ['code' => $type['code']],
                    [
                        'loan_product_id' => $loanProducts[$type['product_code']]->id,
                        'name' => $type['name'],
                        'description' => $type['name'].' sample rates',
                        'accrual_period' => $type['accrual_period'],
                        'is_active' => true,
                    ]
                );

                $counts['loan_rate_types'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$type['code'] => $record];
            });

            $loanRateSeeds = [
                'MOU-STD' => [
                    ['tenure' => 1, 'processing' => 6, 'daily' => 0.00055, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 2, 'processing' => 7, 'daily' => 0.00052, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 3, 'processing' => 7.5, 'daily' => 0.00050, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 6, 'processing' => 8, 'daily' => 0.00048, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 9, 'processing' => 9, 'daily' => 0.00046, 'weekly' => null, 'arrear' => 0.038],
                    ['tenure' => 12, 'processing' => 10, 'daily' => 0.00044, 'weekly' => null, 'arrear' => 0.035],
                    ['tenure' => 18, 'processing' => 11, 'daily' => 0.00042, 'weekly' => null, 'arrear' => 0.032],
                    ['tenure' => 24, 'processing' => 12, 'daily' => 0.00040, 'weekly' => null, 'arrear' => 0.03],
                ],
                'MRKT-WKLY' => [
                    ['tenure' => 1, 'processing' => 4, 'daily' => null, 'weekly' => 0.00385, 'arrear' => 0.09],
                    ['tenure' => 2, 'processing' => 4.5, 'daily' => null, 'weekly' => 0.00370, 'arrear' => 0.09],
                    ['tenure' => 3, 'processing' => 5, 'daily' => null, 'weekly' => 0.00355, 'arrear' => 0.09],
                    ['tenure' => 6, 'processing' => 6, 'daily' => null, 'weekly' => 0.00330, 'arrear' => 0.085],
                    ['tenure' => 9, 'processing' => 7, 'daily' => null, 'weekly' => 0.00310, 'arrear' => 0.08],
                    ['tenure' => 12, 'processing' => 8, 'daily' => null, 'weekly' => 0.00290, 'arrear' => 0.075],
                    ['tenure' => 18, 'processing' => 9, 'daily' => null, 'weekly' => 0.00270, 'arrear' => 0.07],
                    ['tenure' => 24, 'processing' => 10, 'daily' => null, 'weekly' => 0.00250, 'arrear' => 0.065],
                ],
                'CHAR-FLEX' => [
                    ['tenure' => 1, 'processing' => 6, 'daily' => 0.00060, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 2, 'processing' => 6.5, 'daily' => 0.00058, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 3, 'processing' => 7, 'daily' => 0.00056, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 6, 'processing' => 7.5, 'daily' => 0.00054, 'weekly' => null, 'arrear' => 0.04],
                    ['tenure' => 9, 'processing' => 8, 'daily' => 0.00052, 'weekly' => null, 'arrear' => 0.038],
                    ['tenure' => 12, 'processing' => 8.5, 'daily' => 0.00050, 'weekly' => null, 'arrear' => 0.036],
                    ['tenure' => 18, 'processing' => 9, 'daily' => 0.00048, 'weekly' => null, 'arrear' => 0.034],
                    ['tenure' => 24, 'processing' => 9.5, 'daily' => 0.00046, 'weekly' => null, 'arrear' => 0.032],
                ],
                'COLL-SEC' => [
                    ['tenure' => 1, 'processing' => 5, 'daily' => 0.00050, 'weekly' => null, 'arrear' => 0.03],
                    ['tenure' => 2, 'processing' => 5.5, 'daily' => 0.00048, 'weekly' => null, 'arrear' => 0.03],
                    ['tenure' => 3, 'processing' => 6, 'daily' => 0.00046, 'weekly' => null, 'arrear' => 0.03],
                    ['tenure' => 6, 'processing' => 6.5, 'daily' => 0.00044, 'weekly' => null, 'arrear' => 0.03],
                    ['tenure' => 9, 'processing' => 7, 'daily' => 0.00042, 'weekly' => null, 'arrear' => 0.029],
                    ['tenure' => 12, 'processing' => 7.5, 'daily' => 0.00040, 'weekly' => null, 'arrear' => 0.028],
                    ['tenure' => 18, 'processing' => 8, 'daily' => 0.00038, 'weekly' => null, 'arrear' => 0.027],
                    ['tenure' => 24, 'processing' => 8.5, 'daily' => 0.00036, 'weekly' => null, 'arrear' => 0.026],
                ],
            ];

            foreach ($loanRateSeeds as $typeCode => $rows) {
                foreach ($rows as $row) {
                    $rate = LoanRate::firstOrCreate(
                        [
                            'loan_rate_type_id' => $rateTypes[$typeCode]->id,
                            'tenure_months' => $row['tenure'],
                        ],
                        [
                            'processing_fee_percentage' => $row['processing'],
                            'daily_rate' => $row['daily'],
                            'weekly_rate' => $row['weekly'],
                            'arrear_rate' => $row['arrear'],
                            'is_active' => true,
                        ]
                    );

                    $counts['loan_rates'] += $rate->wasRecentlyCreated ? 1 : 0;
                }
            }

            // --- Companies ---
            $log('Creating companies...', 'info');
            $companies = collect([
                [
                    'name' => 'BlueWave Telecoms',
                    'code' => 'BWT001',
                    'sector' => 'FIN',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'operator',
                    'city' => 'Lusaka',
                    'is_primary' => false,
                ],
                [
                    'name' => 'Copperbelt Traders Cooperative',
                    'code' => 'CTC045',
                    'sector' => 'RET',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'type' => 'partner',
                    'city' => 'Ndola',
                ],
                [
                    'name' => 'Capital Insure Holdings',
                    'code' => 'CIH210',
                    'sector' => 'FIN',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'operator',
                    'city' => 'Lusaka',
                ],
                [
                    'name' => 'Eastern Agro Inputs',
                    'code' => 'EAI332',
                    'sector' => 'AGR',
                    'loan_rate_type' => 'CHAR-FLEX',
                    'type' => 'partner',
                    'city' => 'Chipata',
                ],
                [
                    'name' => 'Lusaka Logistics Hub',
                    'code' => 'LLH118',
                    'sector' => 'LOG',
                    'loan_rate_type' => 'COLL-SEC',
                    'type' => 'operator',
                    'city' => 'Lusaka',
                ],
                [
                    'name' => 'Zambezi Retail Group',
                    'code' => 'ZRG512',
                    'sector' => 'RET',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'partner',
                    'city' => 'Livingstone',
                ],
                [
                    'name' => 'Mukuba University Partners',
                    'code' => 'MUP640',
                    'sector' => 'EDU',
                    'loan_rate_type' => 'CHAR-FLEX',
                    'type' => 'operator',
                    'city' => 'Kitwe',
                ],
                [
                    'name' => 'Southern Medical Alliance',
                    'code' => 'SMA455',
                    'sector' => 'HEA',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'partner',
                    'city' => 'Choma',
                ],
                [
                    'name' => 'Kafue Hydropower Corp',
                    'code' => 'KHC730',
                    'sector' => 'ENG',
                    'loan_rate_type' => 'COLL-SEC',
                    'type' => 'operator',
                    'city' => 'Kafue',
                ],
                [
                    'name' => 'Chingola Traders Guild',
                    'code' => 'CTG278',
                    'sector' => 'RET',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'type' => 'partner',
                    'city' => 'Chingola',
                ],
                [
                    'name' => 'Central Civil Service Bureau',
                    'code' => 'CCS900',
                    'sector' => 'GOV',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'operator',
                    'city' => 'Lusaka',
                ],
                [
                    'name' => 'Greenbelt Cooperative Union',
                    'code' => 'GCU560',
                    'sector' => 'AGR',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'type' => 'partner',
                    'city' => 'Kasama',
                ],
                [
                    'name' => 'Westbridge Manufacturing Ltd',
                    'code' => 'WML845',
                    'sector' => 'MAN',
                    'loan_rate_type' => 'COLL-SEC',
                    'type' => 'operator',
                    'city' => 'Luanshya',
                ],
                [
                    'name' => 'Victoria Hospitality Group',
                    'code' => 'VHG377',
                    'sector' => 'HOS',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'partner',
                    'city' => 'Livingstone',
                ],
                [
                    'name' => 'Copperline Security Services',
                    'code' => 'CSS812',
                    'sector' => 'GOV',
                    'loan_rate_type' => 'CHAR-FLEX',
                    'type' => 'partner',
                    'city' => 'Lusaka',
                ],
                [
                    'name' => 'Platinum Realty Partners',
                    'code' => 'PRP980',
                    'sector' => 'ICT',
                    'loan_rate_type' => 'MOU-STD',
                    'type' => 'operator',
                    'city' => 'Ndola',
                ],
            ])->mapWithKeys(function (array $company) use (&$counts, $sectorMap, $rateTypes) {
                $record = Company::firstOrCreate(
                    ['code' => $company['code']],
                    [
                        'name' => $company['name'],
                        'slug' => Str::slug($company['name']),
                        'type' => $company['type'],
                        'status' => $company['status'] ?? 'active',
                        'approval_status' => 'approved',
                        'sector_id' => $sectorMap[$company['sector']]->id ?? null,
                        'loan_rate_type_id' => isset($company['loan_rate_type']) && isset($rateTypes[$company['loan_rate_type']])
                            ? $rateTypes[$company['loan_rate_type']]->id
                            : null,
                        'maximum_loan_tenure_months' => 24,
                        'maximum_debit_ratio' => 45,
                        'instalment_cross_over_percentage' => 5,
                        'arrangement_fee_percentage' => 2,
                        'monthly_cut_off_day' => 25,
                        'pay_day' => 30,
                        'country' => $company['country'] ?? 'Zambia',
                        'city' => $company['city'] ?? 'Lusaka',
                        'contact_email' => 'contact@'.Str::slug($company['name']).'.com',
                        'contact_phone' => '+260-211-000000',
                        'is_primary' => $company['is_primary'] ?? false,
                    ]
                );

                $counts['companies'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$company['code'] => $record];
            });

            $defaultCompanyId = $companies->values()->first()?->id;

            // --- Markets ---
            $log('Creating markets...', 'info');
            $marketSeeds = [
                [
                    'name' => 'Kamwala Trading Market',
                    'code' => 'MRK-KAM',
                    'province' => 'LUSA',
                    'district' => 'LUS-CEN',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'city' => 'Lusaka',
                ],
                [
                    'name' => 'Masala Market',
                    'code' => 'MRK-MAS',
                    'province' => 'COPP',
                    'district' => 'NDL',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'city' => 'Ndola',
                ],
            ];

            $markets = collect($marketSeeds)->mapWithKeys(function ($seed) use ($provinceMap, $districtMap, $rateTypes, &$counts) {
                $record = Market::firstOrCreate(
                    ['code' => $seed['code']],
                    [
                        'name' => $seed['name'],
                        'address_line1' => $seed['name'].' Address',
                        'city' => $seed['city'],
                        'province_id' => $provinceMap[$seed['province']]->id,
                        'district_id' => $districtMap[$seed['district']]->id,
                        'contact_person_name' => 'Market Manager '.$seed['name'],
                        'contact_person_phone' => '+260-971-000000',
                        'loan_rate_type_id' => $rateTypes[$seed['loan_rate_type']]->id,
                        'is_active' => true,
                    ]
                );

                $counts['markets'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$seed['code'] => $record];
            });

            // --- Get default branch and relationship managers ---
            $defaultBranch = Branch::where('is_active', true)->first();
            if (!$defaultBranch) {
                // Create a default branch if none exists
                $defaultBranch = Branch::firstOrCreate(
                    ['code' => 'DEFAULT'],
                    [
                        'name' => 'Head Office',
                        'is_active' => true,
                    ]
                );
            }

            // Get all relationship managers
            $relationshipManagers = Admin::where('is_relationship_manager', true)
                ->where('is_active', true)
                ->get();

            // --- Customer Groups for character & collateral products ---
            $log('Creating customer groups...', 'info');
            $customerGroups = collect([
                [
                    'code' => 'CHAR-GRP-A',
                    'name' => 'Character Builders',
                    'loan_product_code' => 'CHAR-001',
                    'loan_rate_type' => 'CHAR-FLEX',
                    'risk_level' => 'low',
                    'max_amount' => 80000,
                ],
                [
                    'code' => 'COLL-GRP-SEC',
                    'name' => 'Collateral Secure Group',
                    'loan_product_code' => 'COLL-001',
                    'loan_rate_type' => 'COLL-SEC',
                    'risk_level' => 'medium',
                    'max_amount' => 150000,
                ],
                [
                    'code' => 'CHAR-GRP-B',
                    'name' => 'Character Growth Circle',
                    'loan_product_code' => 'CHAR-001',
                    'loan_rate_type' => 'CHAR-FLEX',
                    'risk_level' => 'medium',
                    'max_amount' => 60000,
                ],
                [
                    'code' => 'CHAR-GRP-C',
                    'name' => 'Character Enterprise Network',
                    'loan_product_code' => 'CHAR-001',
                    'loan_rate_type' => 'CHAR-FLEX',
                    'risk_level' => 'high',
                    'max_amount' => 45000,
                ],
                [
                    'code' => 'COLL-GRP-PREM',
                    'name' => 'Collateral Premium Clients',
                    'loan_product_code' => 'COLL-001',
                    'loan_rate_type' => 'COLL-SEC',
                    'risk_level' => 'low',
                    'max_amount' => 250000,
                ],
                [
                    'code' => 'COLL-GRP-STD',
                    'name' => 'Collateral Standard Clients',
                    'loan_product_code' => 'COLL-001',
                    'loan_rate_type' => 'COLL-SEC',
                    'risk_level' => 'medium',
                    'max_amount' => 180000,
                ],
                [
                    'code' => 'COLL-GRP-MICRO',
                    'name' => 'Collateral Micro Enterprise',
                    'loan_product_code' => 'COLL-001',
                    'loan_rate_type' => 'COLL-SEC',
                    'risk_level' => 'high',
                    'max_amount' => 60000,
                ],
                [
                    'code' => 'MOU-EXEC',
                    'name' => 'MOU Executive Cohort',
                    'loan_product_code' => 'MOU-001',
                    'loan_rate_type' => 'MOU-STD',
                    'risk_level' => 'low',
                    'max_amount' => 200000,
                ],
                [
                    'code' => 'MOU-ASSOC',
                    'name' => 'MOU Associates Pool',
                    'loan_product_code' => 'MOU-001',
                    'loan_rate_type' => 'MOU-STD',
                    'risk_level' => 'medium',
                    'max_amount' => 120000,
                ],
                [
                    'code' => 'MRKT-CORE',
                    'name' => 'Marketeer Core Vendors',
                    'loan_product_code' => 'MARK-001',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'risk_level' => 'medium',
                    'max_amount' => 80000,
                ],
                [
                    'code' => 'MRKT-START',
                    'name' => 'Marketeer Starters',
                    'loan_product_code' => 'MARK-001',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'risk_level' => 'high',
                    'max_amount' => 40000,
                ],
                [
                    'code' => 'MRKT-GROW',
                    'name' => 'Marketeer Growth Network',
                    'loan_product_code' => 'MARK-001',
                    'loan_rate_type' => 'MRKT-WKLY',
                    'risk_level' => 'medium',
                    'max_amount' => 90000,
                ],
            ])->mapWithKeys(function (array $group, $index) use (&$counts, $loanProducts, $rateTypes, $defaultBranch, $relationshipManagers) {
                // Assign relationship manager in round-robin fashion
                $relationshipManagerId = null;
                if ($relationshipManagers->isNotEmpty()) {
                    $managerIndex = $index % $relationshipManagers->count();
                    $relationshipManagerId = $relationshipManagers->get($managerIndex)?->id;
                }

                $record = CustomerGroup::firstOrCreate(
                    ['code' => $group['code']],
                    [
                        'loan_product_id' => $loanProducts[$group['loan_product_code']]->id,
                        'loan_rate_type_id' => $rateTypes[$group['loan_rate_type']]->id,
                        'branch_id' => $defaultBranch->id,
                        'relationship_manager_id' => $relationshipManagerId,
                        'name' => $group['name'],
                        'description' => $group['name'].' sample group',
                        'risk_level' => $group['risk_level'],
                        'max_loan_amount' => $group['max_amount'],
                        'max_loan_tenure_months' => 12,
                        'is_active' => true,
                    ]
                );

                // Update relationship manager if it was changed or not set
                if ($relationshipManagerId && $record->relationship_manager_id !== $relationshipManagerId) {
                    $record->update(['relationship_manager_id' => $relationshipManagerId]);
                }

                // Update branch if it was changed or not set
                if ($defaultBranch && $record->branch_id !== $defaultBranch->id) {
                    $record->update(['branch_id' => $defaultBranch->id]);
                }

                $counts['customer_groups'] += $record->wasRecentlyCreated ? 1 : 0;

                return [$group['code'] => $record];
            });

            // --- Test Admin Users ---
            $log('Creating test admin users...', 'info');
            $adminSeeds = [
                [
                    'email' => 'superadmin@zamlend.test',
                    'first_name' => 'Super',
                    'last_name' => 'Admin',
                    'employee_number' => 'ADM-001',
                    'nrc' => '999999/11/1',
                    'phone' => '260960000111',
                    'company_code' => 'BWT001',
                    'is_relationship_manager' => false,
                    'roles' => ['super-admin'],
                ],
                [
                    'email' => 'relationship.manager@zamlend.test',
                    'first_name' => 'Linda',
                    'last_name' => 'Mwanza',
                    'employee_number' => 'ADM-002',
                    'nrc' => '999999/22/2',
                    'phone' => '260960000222',
                    'company_code' => 'CTC045',
                    'is_relationship_manager' => true,
                    'roles' => ['relationship-manager'],
                ],
                [
                    'email' => 'loan.officer@zamlend.test',
                    'first_name' => 'Patrick',
                    'last_name' => 'Hamoonga',
                    'employee_number' => 'ADM-003',
                    'nrc' => '999999/33/3',
                    'phone' => '260960000333',
                    'company_code' => 'BWT001',
                    'is_relationship_manager' => false,
                    'roles' => ['loan-officer'],
                ],
                [
                    'email' => 'collections.lead@zamlend.test',
                    'first_name' => 'Bridget',
                    'last_name' => 'Mulenga',
                    'employee_number' => 'ADM-004',
                    'nrc' => '999999/44/4',
                    'phone' => '260960000444',
                    'company_code' => 'CTC045',
                    'is_relationship_manager' => false,
                    'roles' => ['collections-officer'],
                ],
                [
                    'email' => 'company.admin@bluewave.test',
                    'first_name' => 'Henry',
                    'last_name' => 'Zimba',
                    'employee_number' => 'ADM-005',
                    'nrc' => '999999/55/5',
                    'phone' => '260960000555',
                    'company_code' => 'BWT001',
                    'is_relationship_manager' => false,
                    'roles' => ['company-admin'],
                ],
                [
                    'email' => 'company.admin@retailgroup.test',
                    'first_name' => 'Natasha',
                    'last_name' => 'Mwansa',
                    'employee_number' => 'ADM-006',
                    'nrc' => '999999/66/6',
                    'phone' => '260960000666',
                    'company_code' => 'ZRG512',
                    'is_relationship_manager' => false,
                    'roles' => ['company-admin'],
                ],
                [
                    'email' => 'relationship.manager2@zamlend.test',
                    'first_name' => 'Kelvin',
                    'last_name' => 'Kalima',
                    'employee_number' => 'ADM-007',
                    'nrc' => '999999/77/7',
                    'phone' => '260960000777',
                    'company_code' => 'CIH210',
                    'is_relationship_manager' => true,
                    'roles' => ['relationship-manager'],
                ],
                [
                    'email' => 'system.support@zamlend.test',
                    'first_name' => 'Diana',
                    'last_name' => 'Phiri',
                    'employee_number' => 'ADM-008',
                    'nrc' => '999999/88/8',
                    'phone' => '260960000888',
                    'company_code' => null,
                    'is_relationship_manager' => false,
                    'roles' => ['support-analyst'],
                ],
                [
                    'email' => 'compliance.officer@zamlend.test',
                    'first_name' => 'Lubinda',
                    'last_name' => 'Moyo',
                    'employee_number' => 'ADM-009',
                    'nrc' => '999999/99/9',
                    'phone' => '260960000999',
                    'company_code' => null,
                    'is_relationship_manager' => false,
                    'roles' => ['auditor'],
                ],
                [
                    'email' => 'risk.controller@zamlend.test',
                    'first_name' => 'Agness',
                    'last_name' => 'Chansa',
                    'employee_number' => 'ADM-010',
                    'nrc' => '999999/10/0',
                    'phone' => '260960000010',
                    'company_code' => null,
                    'is_relationship_manager' => false,
                    'roles' => ['auditor'],
                ],
                [
                    'email' => 'marketeer.officer@zamlend.test',
                    'first_name' => 'Elias',
                    'last_name' => 'Kapasa',
                    'employee_number' => 'ADM-011',
                    'nrc' => '999999/10/1',
                    'phone' => '260960000011',
                    'company_code' => 'CTC045',
                    'is_relationship_manager' => false,
                    'roles' => ['loan-officer'],
                ],
                [
                    'email' => 'collections.agent2@zamlend.test',
                    'first_name' => 'Bwalya',
                    'last_name' => 'Tembo',
                    'employee_number' => 'ADM-012',
                    'nrc' => '999999/10/2',
                    'phone' => '260960000012',
                    'company_code' => 'CTC045',
                    'is_relationship_manager' => false,
                    'roles' => ['collections-officer'],
                ],
            ];

            $admins = collect($adminSeeds)->mapWithKeys(function (array $admin) use (&$counts, $companies, $defaultCompanyId) {
                $record = Admin::firstOrCreate(
                    ['email' => $admin['email']],
                    [
                        'first_name' => $admin['first_name'],
                        'last_name' => $admin['last_name'],
                        'employee_number' => $admin['employee_number'],
                        'nrc' => $admin['nrc'],
                        'phone' => $admin['phone'],
                        'company_id' => isset($admin['company_code'], $companies[$admin['company_code']])
                            ? $companies[$admin['company_code']]->id
                            : $defaultCompanyId,
                        'is_active' => true,
                        'is_relationship_manager' => $admin['is_relationship_manager'],
                        'approval_status' => 'approved',
                        'password' => 'Password@123',
                        'email_verified_at' => now(),
                    ]
                );

                $counts['admins'] += $record->wasRecentlyCreated ? 1 : 0;

                $roleSlugs = collect($admin['roles'] ?? ($admin['role'] ?? []))
                    ->filter()
                    ->values();

                if ($roleSlugs->isNotEmpty()) {
                    try {
                        $record->syncRoles($roleSlugs->all());
                    } catch (\Throwable $e) {
                        // Role seeder might not have run yet; ignore.
                    }
                }

                return [$admin['email'] => $record];
            });

            // Link relationship manager to markets/companies for context
            if ($relationshipManager = $admins['relationship.manager@zamlend.test'] ?? null) {
                foreach ($markets as $market) {
                    if ($market->portfolio_manager_id !== $relationshipManager->id) {
                        $market->portfolio_manager_id = $relationshipManager->id;
                        $market->save();
                    }
                }

                foreach ($companies as $company) {
                    if ($company->relationship_manager_id !== $relationshipManager->id) {
                        $company->relationship_manager_id = $relationshipManager->id;
                        $company->save();
                    }
                }
            }

            // --- Test Customers / Employees across products ---
            $log('Creating test customers/employees...', 'info');
            $customerSeeds = [
                [
                    'email' => 'mou.employee@demo.test',
                    'first_name' => 'Chanda',
                    'last_name' => 'Mwila',
                    'phone' => '260970111111',
                    'national_id' => '111111/11/1',
                    'tpin' => '100000001',
                    'company_code' => 'BWT001',
                    'loan_product_code' => 'MOU-001',
                    'customer_group_code' => null,
                    'market_code' => null,
                ],
                [
                    'email' => 'character.member@demo.test',
                    'first_name' => 'Martha',
                    'last_name' => 'Zimba',
                    'phone' => '260970222222',
                    'national_id' => '222222/22/2',
                    'tpin' => '100000002',
                    'company_code' => null,
                    'loan_product_code' => 'CHAR-001',
                    'customer_group_code' => 'CHAR-GRP-A',
                    'market_code' => null,
                ],
                [
                    'email' => 'collateral.member@demo.test',
                    'first_name' => 'Joseph',
                    'last_name' => 'Phiri',
                    'phone' => '260970333333',
                    'national_id' => '333333/33/3',
                    'tpin' => '100000003',
                    'company_code' => null,
                    'loan_product_code' => 'COLL-001',
                    'customer_group_code' => 'COLL-GRP-SEC',
                    'market_code' => null,
                ],
                [
                    'email' => 'marketeer.customer@demo.test',
                    'first_name' => 'Tandiwe',
                    'last_name' => 'Ngoma',
                    'phone' => '260970444444',
                    'national_id' => '444444/44/4',
                    'tpin' => '100000004',
                    'company_code' => 'CTC045',
                    'loan_product_code' => 'MARK-001',
                    'customer_group_code' => null,
                    'market_code' => 'MRK-KAM',
                ],
            ];

            foreach ($customerSeeds as $seed) {
                $product = $loanProducts[$seed['loan_product_code']];
                $groupId = $seed['customer_group_code'] ? ($customerGroups[$seed['customer_group_code']]->id ?? null) : null;
                
                // Auto-assign DEFAULT group for government products
                if ($product->category === 'government' && !$groupId) {
                    $defaultGroup = CustomerGroup::where('loan_product_id', $product->id)
                        ->where('code', 'GOV-DEFAULT')
                        ->first();
                    if ($defaultGroup) {
                        $groupId = $defaultGroup->id;
                    }
                }
                
                // Get a random province for test customers, or use Lusaka if available
                $randomProvince = $provinceMap->values()->random();
                
                $customer = Customer::firstOrCreate(
                    ['email' => $seed['email']],
                    [
                        'first_name' => $seed['first_name'],
                        'last_name' => $seed['last_name'],
                        'phone' => $seed['phone'],
                        'company_id' => $seed['company_code'] ? ($companies[$seed['company_code']]->id ?? null) : null,
                        'loan_product_id' => $product->id,
                        'customer_group_id' => $groupId,
                        'status' => 'active',
                        'kyc_status' => 'verified',
                        'employment_status' => 'employed',
                        'national_id' => $seed['national_id'],
                        'tpin' => $seed['tpin'],
                        'date_of_birth' => now()->subYears(32),
                        'gross_salary' => 18000,
                        'net_salary' => 14000,
                        'maximum_loan_take' => 14000 * 0.6, // 60% of net salary
                        'address_line1' => 'Plot 10 Independence Ave',
                        'city' => 'Lusaka',
                        'province_id' => $randomProvince->id,
                        'country' => 'Zambia',
                        'password' => Hash::make('1234'), // PIN stored in password field
                        'must_change_pin' => false,
                        'email_verified_at' => now(),
                    ]
                );

                $counts['customers'] += $customer->wasRecentlyCreated ? 1 : 0;

                if ($seed['market_code']) {
                    $detail = MarketeerCustomerDetail::updateOrCreate(
                        ['customer_id' => $customer->id],
                        [
                            'market_id' => $markets[$seed['market_code']]->id,
                            'stand_number' => 'STND-'.$customer->id,
                            'stand_description' => 'Fresh produce stand',
                            'monthly_income' => 12000,
                        ]
                    );

                    $counts['marketeer_customer_details'] += $detail->wasRecentlyCreated ? 1 : 0;
                }
            }

            // --- Bulk synthetic customers (approx 600 total) ---
            $log('Creating bulk synthetic customers (this may take a while)...', 'info');
            $bulkPlans = [
                [
                    'product_code' => 'MOU-001',
                    'count' => 150,
                    'company_code' => 'BWT001',
                    'group_codes' => ['MOU-EXEC', 'MOU-ASSOC'], // Distribute across these groups
                    'market_rotation' => false,
                ],
                [
                    'product_code' => 'CHAR-001',
                    'count' => 150,
                    'company_code' => null,
                    'group_codes' => ['CHAR-GRP-A', 'CHAR-GRP-B', 'CHAR-GRP-C'], // Distribute across all character groups
                    'market_rotation' => false,
                ],
                [
                    'product_code' => 'COLL-001',
                    'count' => 150,
                    'company_code' => null,
                    'group_codes' => ['COLL-GRP-SEC', 'COLL-GRP-PREM', 'COLL-GRP-STD', 'COLL-GRP-MICRO'], // Distribute across all collateral groups
                    'market_rotation' => false,
                ],
                [
                    'product_code' => 'MARK-001',
                    'count' => 150,
                    'company_code' => 'CTC045',
                    'group_codes' => ['MRKT-CORE', 'MRKT-START', 'MRKT-GROW'], // Distribute across all marketeer groups
                    'market_rotation' => true,
                ],
            ];

            foreach ($bulkPlans as $index => $plan) {
                $product = $loanProducts[$plan['product_code']];
                $companyId = $plan['company_code'] ? ($companies[$plan['company_code']]->id ?? null) : null;
                
                // Get all available groups for this product - always query from database to ensure we have latest
                $availableGroups = [];
                
                // First, try to get groups from specified codes if provided
                if (isset($plan['group_codes']) && is_array($plan['group_codes']) && !empty($plan['group_codes'])) {
                    foreach ($plan['group_codes'] as $groupCode) {
                        // Try from array first
                        if (isset($customerGroups[$groupCode])) {
                            $availableGroups[] = $customerGroups[$groupCode]->id;
                        } else {
                            // Fallback to database lookup
                            $group = CustomerGroup::where('code', $groupCode)
                                ->where('loan_product_id', $product->id)
                                ->first();
                            if ($group) {
                                $availableGroups[] = $group->id;
                            }
                        }
                    }
                }
                
                // If no groups found from specified codes, get all groups for this product from database
                if (empty($availableGroups)) {
                    $productGroups = CustomerGroup::where('loan_product_id', $product->id)
                        ->where('is_active', true)
                        ->pluck('id')
                        ->toArray();
                    $availableGroups = $productGroups;
                }
                
                // Auto-assign DEFAULT group for government products if no groups found
                if (empty($availableGroups) && $product->category === 'government') {
                    $defaultGroup = CustomerGroup::where('loan_product_id', $product->id)
                        ->where('code', 'GOV-DEFAULT')
                        ->first();
                    if ($defaultGroup) {
                        $availableGroups = [$defaultGroup->id];
                    }
                }
                
                // If still no groups, create a default group for this product
                if (empty($availableGroups)) {
                    // Get default branch and relationship manager (reuse from outer scope or fetch)
                    $defaultBranchForGroup = Branch::where('is_active', true)->first();
                    if (!$defaultBranchForGroup) {
                        $defaultBranchForGroup = Branch::firstOrCreate(
                            ['code' => 'DEFAULT'],
                            ['name' => 'Head Office', 'is_active' => true]
                        );
                    }
                    
                    $relationshipManagerForGroup = Admin::where('is_relationship_manager', true)
                        ->where('is_active', true)
                        ->first();
                    
                    $defaultGroup = CustomerGroup::firstOrCreate(
                        [
                            'loan_product_id' => $product->id,
                            'code' => $product->code . '-DEFAULT',
                        ],
                        [
                            'name' => $product->name . ' Default Group',
                            'description' => 'Default group for ' . $product->name,
                            'risk_level' => 'medium',
                            'max_loan_amount' => $product->max_amount ?? 100000,
                            'max_loan_tenure_months' => $product->tenure_months ?? 12,
                            'branch_id' => $defaultBranchForGroup?->id,
                            'relationship_manager_id' => $relationshipManagerForGroup?->id,
                            'is_active' => true,
                        ]
                    );
                    
                    // Update if branch or manager was not set
                    if ($defaultBranchForGroup && !$defaultGroup->branch_id) {
                        $defaultGroup->update(['branch_id' => $defaultBranchForGroup->id]);
                    }
                    if ($relationshipManagerForGroup && !$defaultGroup->relationship_manager_id) {
                        $defaultGroup->update(['relationship_manager_id' => $relationshipManagerForGroup->id]);
                    }
                    
                    $availableGroups = [$defaultGroup->id];
                    $log("  Created default group for {$product->name} (no groups existed)", 'info');
                }
                
                // Ensure all customers get assigned to a group - never allow null
                $groupCount = count($availableGroups);
                if ($groupCount === 0) {
                    throw new \Exception("No groups available for product {$product->code}. Cannot create customers without groups.");
                }
                
                $log("  Distributing {$plan['count']} customers across {$groupCount} groups for {$product->name}...", 'info');
                
                $planKey = strtoupper(substr($plan['product_code'], 0, 4));

                for ($i = 1; $i <= $plan['count']; $i++) {
                    $sequence = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                    $email = strtolower($plan['product_code']).".user{$sequence}@demo.test";

                    // Distribute customers evenly across available groups
                    $groupId = null;
                    if ($groupCount > 0) {
                        $groupIndex = ($i - 1) % $groupCount;
                        $groupId = $availableGroups[$groupIndex];
                    }

                    // Randomly select a province for each customer
                    $randomProvince = $provinceMap->values()->random();
                    
                    $customer = Customer::firstOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $faker->firstName(),
                            'last_name' => $faker->lastName(),
                            'phone' => sprintf('2609%02d%04d', $index + 70, $i + 1000),
                            'company_id' => $companyId,
                            'loan_product_id' => $product->id,
                            'customer_group_id' => $groupId,
                            'status' => 'active',
                            'kyc_status' => 'verified',
                            'employment_status' => 'employed',
                            'national_id' => sprintf('%06d/%02d/%d', $i + ($index * 500), $index + 10, 1),
                            'tpin' => (string) (100000100 + $i + ($index * 200)),
                            'date_of_birth' => now()->subYears(rand(25, 55))->subMonths(rand(0, 11)),
                            'gross_salary' => rand(8000, 25000),
                            'net_salary' => $netSalary = rand(6000, 20000),
                            'maximum_loan_take' => $netSalary * 0.6, // 60% of net salary
                            'address_line1' => $faker->streetAddress(),
                            'city' => $faker->city(),
                            'province_id' => $randomProvince->id,
                            'country' => 'Zambia',
                            'password' => Hash::make('1234'), // PIN stored in password field
                            'must_change_pin' => false,
                            'email_verified_at' => now(),
                        ]
                    );

                    $counts['customers'] += $customer->wasRecentlyCreated ? 1 : 0;

                    if ($plan['market_rotation'] && $markets->isNotEmpty()) {
                        $market = $markets->values()->get(($i - 1) % $markets->count());
                        $detail = MarketeerCustomerDetail::updateOrCreate(
                            ['customer_id' => $customer->id],
                            [
                                'market_id' => $market->id,
                                'stand_number' => "{$planKey}-STND-{$sequence}",
                                'stand_description' => $faker->sentence(6),
                                'monthly_income' => rand(8000, 20000),
                            ]
                        );

                        if ($detail->wasRecentlyCreated ?? false) {
                            $counts['marketeer_customer_details']++;
                        }
                    }
                }
            }

            // --- Sample Loans ---
            $log('Creating sample loans (this may take a while)...', 'info');
            $channels = Channel::where('is_active', true)->get();
            if ($channels->isEmpty()) {
                // Create a default channel if none exists
                $defaultChannel = Channel::firstOrCreate(
                    ['code' => 'DEFAULT'],
                    [
                        'name' => 'Default Channel',
                        'description' => 'Default payment channel',
                        'is_active' => true,
                    ]
                );
                $channels = collect([$defaultChannel]);
            }

            // Get active customers with their products and groups
            $activeCustomers = Customer::where('status', 'active')
                ->with(['loanProduct', 'customerGroup'])
                ->get();

            // Create loans for a subset of customers (about 30% of active customers, max 100 loans)
            $loanCount = min((int) ($activeCustomers->count() * 0.3), 100);
            $customersForLoans = $activeCustomers->shuffle()->take($loanCount);
            
            $log("Creating loans for {$loanCount} customers...", 'info');
            $loanProgress = 0;

            foreach ($customersForLoans as $customer) {
                $loanProgress++;
                if ($loanProgress % 10 === 0) {
                    $log("  Processed {$loanProgress}/{$loanCount} loans...", 'info');
                }
                
                try {
                    $product = $customer->loanProduct;
                    $customerGroup = $customer->customerGroup;

                    if (!$product || !$product->is_active) {
                        continue;
                    }

                    // Get loan rate type for the product
                    $rateType = LoanRateType::where('loan_product_id', $product->id)
                        ->where('is_active', true)
                        ->first();

                    if (!$rateType) {
                        continue;
                    }

                    // Calculate maximum loan amount respecting customer's maximum_loan_take
                    $maxLoanAmount = $customer->maximum_loan_take ?? 0;
                    
                    // Also consider customer group and product limits
                    if ($customerGroup && $customerGroup->max_loan_amount) {
                        $maxLoanAmount = min($maxLoanAmount, $customerGroup->max_loan_amount);
                    }
                    if ($product->max_amount) {
                        $maxLoanAmount = min($maxLoanAmount, $product->max_amount);
                    }

                    if ($maxLoanAmount <= 0) {
                        continue;
                    }

                    // Determine maximum allowed tenure
                    $maxTenure = $product->tenure_months;
                    if ($customerGroup && $customerGroup->max_loan_tenure_months) {
                        $maxTenure = min($maxTenure, $customerGroup->max_loan_tenure_months);
                    }

                    // Get all available loan rates (prefer matching customer group's rate type if available)
                    $availableRates = collect();
                    if ($customerGroup && $customerGroup->loan_rate_type_id) {
                        $availableRates = LoanRate::where('loan_rate_type_id', $customerGroup->loan_rate_type_id)
                            ->where('is_active', true)
                            ->where('tenure_months', '<=', $maxTenure)
                            ->orderBy('tenure_months')
                            ->get();
                    }

                    // Fallback to product's rate type
                    if ($availableRates->isEmpty()) {
                        $availableRates = LoanRate::where('loan_rate_type_id', $rateType->id)
                            ->where('is_active', true)
                            ->where('tenure_months', '<=', $maxTenure)
                            ->orderBy('tenure_months')
                            ->get();
                    }

                    if ($availableRates->isEmpty()) {
                        continue;
                    }

                    // Randomly select a loan rate from available options to get variety in tenures
                    $loanRate = $availableRates->random();
                    $tenureMonths = $loanRate->tenure_months;

                    // Generate loan amount (30% to 90% of max)
                    $principalAmount = $faker->numberBetween(
                        (int) ($maxLoanAmount * 0.3),
                        (int) ($maxLoanAmount * 0.9)
                    );

                    // Calculate processing fee
                    $processingFee = ($principalAmount * $loanRate->processing_fee_percentage) / 100;

                    // Calculate loan dates (loan started 1-60 days ago)
                    $loanStartDate = Carbon::today()->subDays($faker->numberBetween(1, 60));
                    $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
                    $days = $loanStartDate->diffInDays($loanEndDate);

                    // Calculate payment dates
                    $firstPaymentDate = null;
                    $lastPaymentDate = null;

                    if (in_array($product->category, ['government', 'mou']) && $customerGroup) {
                        $cutOffDay = $customerGroup->loan_cut_off_day ?? 25;
                        $paymentDay = $customerGroup->loan_payment_date ?? 30;

                        $currentDay = $loanStartDate->day;
                        if ($currentDay > $cutOffDay) {
                            $firstPaymentDate = Carbon::create($loanStartDate->year, $loanStartDate->month + 1, $paymentDay);
                        } else {
                            $firstPaymentDate = Carbon::create($loanStartDate->year, $loanStartDate->month, $paymentDay);
                            if ($firstPaymentDate->isPast()) {
                                $firstPaymentDate->addMonth();
                            }
                        }
                        $lastPaymentDate = $firstPaymentDate->copy()->addMonths($tenureMonths - 1);
                    } else {
                        $firstPaymentDate = $loanStartDate->copy()->addMonth();
                        $lastPaymentDate = $firstPaymentDate->copy()->addMonths($tenureMonths - 1);
                    }

                    // Calculate interest based on accrual type
                    $accrualType = $product->accrual_type ?? 'at_beginning';
                    $interest = 0;

                    if ($accrualType === 'at_beginning') {
                        if ($rateType->accrual_period === 'daily' && $loanRate->daily_rate) {
                            $interest = $principalAmount * $loanRate->daily_rate * $days;
                        } elseif ($rateType->accrual_period === 'weekly' && $loanRate->weekly_rate) {
                            $weeks = ceil($days / 7);
                            $interest = $principalAmount * $loanRate->weekly_rate * $weeks;
                        }
                    } else {
                        // Daily accrual - calculate 1 day interest initially
                        if ($rateType->accrual_period === 'daily' && $loanRate->daily_rate) {
                            $interest = $principalAmount * $loanRate->daily_rate * 1;
                        } elseif ($rateType->accrual_period === 'weekly' && $loanRate->weekly_rate) {
                            $interest = $principalAmount * $loanRate->weekly_rate * (1/7);
                        }
                    }

                    // Calculate total amount
                    $totalAmount = $principalAmount + $processingFee + $interest;
                    $outstandingBalance = $totalAmount;

                    // Create the loan
                    $loan = Loan::create([
                        'customer_id' => $customer->id,
                        'loan_product_id' => $product->id,
                        'customer_group_id' => $customerGroup?->id,
                        'loan_rate_id' => $loanRate->id,
                        'channel_id' => $channels->random()->id,
                        'loan_number' => Loan::generateLoanNumber($product),
                        'principal_amount' => $principalAmount,
                        'processing_fee' => $processingFee,
                        'processing_fee_percentage' => $loanRate->processing_fee_percentage,
                        'daily_rate' => $loanRate->daily_rate,
                        'weekly_rate' => $loanRate->weekly_rate,
                        'accrual_period' => $rateType->accrual_period,
                        'interest_accrued' => $interest,
                        'total_amount' => $totalAmount,
                        'amount_paid' => 0,
                        'outstanding_balance' => $outstandingBalance,
                        'tenure_months' => $tenureMonths,
                        'loan_start_date' => $loanStartDate,
                        'loan_end_date' => $loanEndDate,
                        'first_payment_date' => $firstPaymentDate,
                        'last_payment_date' => $lastPaymentDate,
                        'accrual_type' => $accrualType,
                        'last_accrual_date' => $accrualType === 'daily' ? $loanStartDate : null,
                        'status' => 'active',
                        'approved_by' => Admin::first()?->id ?? 1,
                        'approved_at' => $loanStartDate,
                        'disbursement_phone_number' => $customer->phone,
                        'disbursement_status' => 'completed',
                        'disbursed_at' => $loanStartDate,
                        'metadata' => [
                            'seeded' => true,
                            'calculated_days' => $days,
                        ],
                    ]);

                    // Create accrual records based on accrual type
                    if ($accrualType === 'at_beginning') {
                        $loan->createAtBeginningAccruals();
                    }

                    // Create payment schedule
                    if ($firstPaymentDate) {
                        $loan->createPaymentSchedule();
                    }

                    $counts['loans']++;
                } catch (\Exception $e) {
                    // Continue with next customer if loan creation fails
                    continue;
                }
            }

            // --- Sample Repayments ---
            $log('Creating sample repayments...', 'info');
            // Get active loans with outstanding balance for repayment simulation
            $loansForRepayment = Loan::where('status', 'active')
                ->where('outstanding_balance', '>', 0)
                ->with(['customer', 'loanProduct', 'paymentSchedules'])
                ->take(30) // Simulate repayments on up to 30 loans
                ->get();

            if ($loansForRepayment->isNotEmpty()) {
                // Get repayment channels
                $repaymentChannels = Channel::where('is_active', true)
                    ->where('can_repay', true)
                    ->get();

                if ($repaymentChannels->isEmpty()) {
                    // Use any active channel if no repayment-specific channels
                    $repaymentChannels = Channel::where('is_active', true)->get();
                }

                if ($repaymentChannels->isNotEmpty()) {
                    $scenarios = [
                        'full_payment',      // Fully pay off the loan
                        'multiple_partial',  // Multiple partial payments over time
                        'single_partial',    // One partial payment
                        'overdue_then_full', // Pay overdue first, then full
                        'early_full',        // Pay full amount early
                    ];

                    $repaymentProgress = 0;
                    $repaymentTotal = $loansForRepayment->count();
                    $log("Processing repayments for {$repaymentTotal} loans...", 'info');
                    
                    foreach ($loansForRepayment as $index => $loan) {
                        $repaymentProgress++;
                        if ($repaymentProgress % 5 === 0) {
                            $log("  Processed {$repaymentProgress}/{$repaymentTotal} repayments...", 'info');
                        }
                        
                        try {
                            // Select a scenario based on index for variety
                            $scenarioIndex = $index % count($scenarios);
                            $scenario = $scenarios[$scenarioIndex];
                            $channel = $repaymentChannels->random();

                            switch ($scenario) {
                                case 'full_payment':
                                    self::createFullPayment($loan, $channel, $faker, $counts);
                                    break;
                                
                                case 'multiple_partial':
                                    self::createMultiplePartialPayments($loan, $channel, $faker, $counts);
                                    break;
                                
                                case 'single_partial':
                                    self::createSinglePartialPayment($loan, $channel, $faker, $counts);
                                    break;
                                
                                case 'overdue_then_full':
                                    self::createOverdueThenFull($loan, $channel, $faker, $counts);
                                    break;
                                
                                case 'early_full':
                                    self::createEarlyFullPayment($loan, $channel, $faker, $counts);
                                    break;
                            }
                        } catch (\Exception $e) {
                            // Continue with next loan if repayment creation fails
                            continue;
                        }
                    }
                }
            }

            $log('Sample data generation completed!', 'success');
            return $counts;
        });
    }

    /**
     * Create a full payment repayment
     */
    private static function createFullPayment(Loan $loan, Channel $channel, $faker, array &$counts): void
    {
        $amount = $loan->outstanding_balance;
        $repayment = self::createRepayment($loan, $channel, $amount, $faker);
        self::applyRepaymentToLoan($loan, $repayment, $amount, $counts);
    }

    /**
     * Create multiple partial payments
     */
    private static function createMultiplePartialPayments(Loan $loan, Channel $channel, $faker, array &$counts): void
    {
        $numPayments = $faker->numberBetween(2, 3);
        $remainingBalance = $loan->outstanding_balance;

        for ($i = 0; $i < $numPayments; $i++) {
            if ($remainingBalance <= 0) {
                break;
            }

            // Calculate payment amount
            if ($i === $numPayments - 1) {
                // Last payment - remainder or large partial
                $amount = min($remainingBalance, $faker->numberBetween(
                    (int) ($remainingBalance * 0.4),
                    (int) ($remainingBalance * 0.9)
                ));
            } else {
                // Early payments
                $amount = $faker->numberBetween(
                    (int) ($remainingBalance * 0.2),
                    (int) ($remainingBalance * 0.5)
                );
            }

            $repaymentDate = Carbon::today()->subDays($faker->numberBetween(1, 30 - ($i * 10)));
            $repayment = self::createRepayment($loan, $channel, $amount, $faker, $repaymentDate);
            self::applyRepaymentToLoan($loan, $repayment, $amount, $counts);
            
            $remainingBalance -= $amount;
            $loan->refresh();
        }
    }

    /**
     * Create a single partial payment
     */
    private static function createSinglePartialPayment(Loan $loan, Channel $channel, $faker, array &$counts): void
    {
        $amount = $faker->numberBetween(
            (int) ($loan->outstanding_balance * 0.2),
            (int) ($loan->outstanding_balance * 0.6)
        );

        $repayment = self::createRepayment($loan, $channel, $amount, $faker);
        self::applyRepaymentToLoan($loan, $repayment, $amount, $counts);
    }

    /**
     * Create overdue payment then full payment
     */
    private static function createOverdueThenFull(Loan $loan, Channel $channel, $faker, array &$counts): void
    {
        // Get overdue amount if method exists
        $overdueAmount = 0;
        if (method_exists($loan, 'getOverdueAmount')) {
            $overdueAmount = $loan->getOverdueAmount();
        } else {
            // Estimate overdue from payment schedules
            if ($loan->paymentSchedules) {
                $overdueAmount = $loan->paymentSchedules
                    ->where('due_date', '<', Carbon::today())
                    ->where('remaining_amount', '>', 0)
                    ->sum('remaining_amount');
            }
        }

        if ($overdueAmount > 0) {
            $firstPayment = min($overdueAmount * 1.2, $loan->outstanding_balance);
            $repaymentDate = Carbon::today()->subDays($faker->numberBetween(5, 15));
            
            $repayment = self::createRepayment($loan, $channel, $firstPayment, $faker, $repaymentDate);
            self::applyRepaymentToLoan($loan, $repayment, $firstPayment, $counts);
            $loan->refresh();
        }

        // Then pay the rest if there's any remaining
        if ($loan->outstanding_balance > 0) {
            $remainingPayment = $loan->outstanding_balance;
            $repaymentDate = Carbon::today()->subDays($faker->numberBetween(1, 5));
            
            $repayment = self::createRepayment($loan, $channel, $remainingPayment, $faker, $repaymentDate);
            self::applyRepaymentToLoan($loan, $repayment, $remainingPayment, $counts);
        }
    }

    /**
     * Create early full payment
     */
    private static function createEarlyFullPayment(Loan $loan, Channel $channel, $faker, array &$counts): void
    {
        $amount = $loan->outstanding_balance;
        $repaymentDate = $loan->loan_start_date->copy()->addDays($faker->numberBetween(5, 15));
        
        $repayment = self::createRepayment($loan, $channel, $amount, $faker, $repaymentDate);
        self::applyRepaymentToLoan($loan, $repayment, $amount, $counts);
    }

    /**
     * Create a repayment record
     */
    private static function createRepayment(Loan $loan, Channel $channel, float $amount, $faker, ?Carbon $repaymentDate = null): Repayment
    {
        $repaymentDate = $repaymentDate ?? Carbon::today()->subDays($faker->numberBetween(1, 30));

        return Repayment::create([
            'customer_id' => $loan->customer_id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => $amount,
            'phone_number' => $loan->customer->phone ?? $faker->numerify('0#########'),
            'external_reference' => 'EXT-' . strtoupper($channel->code) . '-' . $repaymentDate->format('YmdHis') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'external_transaction_id' => 'TXN-' . $repaymentDate->timestamp . '-' . random_int(1000, 9999),
            'status' => 'completed',
            'status_message' => 'Payment processed successfully',
            'processed_at' => $repaymentDate,
            'metadata' => [
                'seeded' => true,
                'seeded_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Apply repayment to loan
     */
    private static function applyRepaymentToLoan(Loan $loan, Repayment $repayment, float $amount, array &$counts): void
    {
        // Get loan state before payment
        $outstandingBalanceBefore = $loan->outstanding_balance;
        
        // Use the Loan model's helper method to calculate repayment allocation
        $allocation = $loan->calculateRepaymentAllocation($amount);
        
        $principalAmount = $allocation['principal_amount'];
        $interestAmount = $allocation['interest_amount'];
        $processingFeeAmount = $allocation['processing_fee_amount'];
        
        // Verify the allocation sums correctly
        $totalAllocated = $principalAmount + $interestAmount + $processingFeeAmount;
        if (abs($totalAllocated - $amount) > 0.01) {
            // If there's a rounding discrepancy, adjust principal
            $principalAmount += ($amount - $totalAllocated);
            $principalAmount = max(0, $principalAmount);
        }

        // Update payment schedule FIRST
        if (method_exists($loan, 'updatePaymentSchedule')) {
            $loan->updatePaymentSchedule($amount);
        }

        // Sync outstanding balance from schedule
        $loan->refresh();
        if (method_exists($loan, 'syncOutstandingBalanceFromSchedule')) {
            $loan->syncOutstandingBalanceFromSchedule();
        } else {
            // Fallback: manually update outstanding balance
            $loan->outstanding_balance = max(0, $loan->outstanding_balance - $amount);
        }

        // Update loan balances
        $newAmountPaid = $loan->amount_paid + $amount;
        $loan->update([
            'amount_paid' => $newAmountPaid,
            // Outstanding balance already synced from schedule
        ]);

        // If fully paid, mark as settled
        if ($loan->outstanding_balance <= 0) {
            $loan->update([
                'status' => 'settled',
                'loan_settled_date' => $repayment->processed_at ? $repayment->processed_at->toDateString() : now()->toDateString(),
            ]);
        }

        // Create loan repayment record
        LoanRepayment::create([
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'amount' => $amount,
            'principal_amount' => round($principalAmount, 2),
            'interest_amount' => round($interestAmount, 2),
            'processing_fee_amount' => round($processingFeeAmount, 2),
            'outstanding_balance_before' => $outstandingBalanceBefore,
            'outstanding_balance_after' => $loan->outstanding_balance,
            'notes' => "Sample repayment for loan {$loan->loan_number}",
        ]);

        $counts['repayments']++;
    }
}

