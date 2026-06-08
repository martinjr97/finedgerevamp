<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'company_id' => 1,
                'name' => 'Collateral Backed Loan',
                'code' => 'COLL-001',
                'category' => 'collateral',
                'description' => 'Asset-backed lending for higher ticket sizes.',
                'tenure_months' => 3,
                'max_amount' => 5_000_000,
                'requires_collateral' => true,
                'requires_reference' => false,
            ],
            [
                'company_id' => 1,
                'name' => 'SME Working Capital',
                'code' => 'SME-001',
                'category' => 'sme',
                'description' => 'Company-as-borrower facility with representative access.',
                'tenure_months' => 6,
                'max_amount' => 8_000_000,
                'requires_collateral' => false,
                'requires_reference' => false,
            ],
                        [
                'company_id' => 1,
                'name' => 'MOU Partner Loan',
                'code' => 'MOU-001',
                'category' => 'mou',
                'description' => 'Partner-backed facility under memorandum of understanding.',
                'tenure_months' => 3,
                'max_amount' => 1_500_000,
                'requires_collateral' => false,
                'requires_reference' => true,
            ],
            [
                'company_id' => 1,
                'name' => 'Personal Loans',
                'code' => 'CHAR-001',
                'category' => 'character',
                'description' => 'Reputation and relationship powered lending product.',
                'tenure_months' => 3,
                'max_amount' => 500_000,
                'requires_collateral' => false,
                'requires_reference' => true,
            ],
            [
                'company_id' => 1,
                'name' => 'Government Payroll Loan',
                'code' => 'GOV-001',
                'category' => 'government',
                'description' => 'Low interest payroll-backed facility for government employees.',
                'tenure_months' => 2,
                'max_amount' => 3_000_000,
                'requires_collateral' => false,
                'requires_reference' => false,
            ],
            [
                'company_id' => 1,
                'name' => 'Group Loans',
                'code' => 'GROUP-001',
                'category' => 'group_loans',
                'description' => 'Group lending product with member-level allocations and disbursements.',
                'tenure_months' => 3,
                'max_amount' => 2_000_000,
                'requires_collateral' => false,
                'requires_reference' => false,
            ],
        ];

        foreach ($products as $product) {
            $loanProduct = LoanProduct::updateOrCreate(
                ['code' => $product['code']],
                $product + [
                    'is_active' => true,
                    'rules' => [
                        'max_ltv' => $product['requires_collateral'] ? 0.7 : null,
                        'requires_payroll_deduction' => $product['category'] === 'government',
                    ],
                ]
            );

            // Create DEFAULT customer group for government products
            if ($product['category'] === 'government') {
                CustomerGroup::updateOrCreate(
                    [
                        'code' => 'GOV-DEFAULT',
                        'loan_product_id' => $loanProduct->id,
                    ],
                    [
                        'name' => 'DEFAULT',
                        'description' => 'Default customer group for all government employees',
                        'risk_level' => 'medium',
                        'max_loan_amount' => $product['max_amount'] ?? null,
                        'max_loan_tenure_months' => null,
                        'loan_cut_off_day' => 5,
                        'loan_payment_date' => 30,
                        'maximum_debit_ratio' => 60.00,
                        'instalment_cross_over_percentage' => 1.00,
                        'is_active' => true,
                    ]
                );
            }

            // Create default group for Group Loans product so onboarding is usable immediately.
            if ($product['category'] === 'group_loans') {
                CustomerGroup::updateOrCreate(
                    [
                        'code' => 'GL-DEFAULT',
                        'loan_product_id' => $loanProduct->id,
                    ],
                    [
                        'name' => 'Default Group',
                        'description' => 'Default active group for Group Loans onboarding fallback.',
                        'risk_level' => 'medium',
                        'max_loan_amount' => $product['max_amount'] ?? null,
                        'max_loan_tenure_months' => $product['tenure_months'] ?? null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
