<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;
use RuntimeException;

class GroupLoansProductSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()
            ->where('is_primary', true)
            ->orWhere('status', 'active')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if (! $company) {
            throw new RuntimeException('Cannot seed Group Loans product: no company record exists.');
        }

        $product = LoanProduct::query()->firstOrNew([
            'code' => 'GROUP-001',
        ]);

        $product->fill([
            // Preserve existing company ownership if product already exists.
            'company_id' => $product->exists ? $product->company_id : $company->id,
            'name' => 'Group Loans',
            'category' => 'group_loans',
            'description' => 'Group lending product with member-level allocations and disbursements.',
            'tenure_months' => 3,
            'max_amount' => 2_000_000,
            'requires_collateral' => false,
            'requires_reference' => false,
            'is_active' => true,
            'rules' => [
                'max_ltv' => null,
                'requires_payroll_deduction' => false,
            ],
        ]);
        $product->save();

        CustomerGroup::query()->updateOrCreate(
            ['code' => 'GL-DEFAULT'],
            [
                'loan_product_id' => $product->id,
                'name' => 'Default Group',
                'description' => 'Default active group for Group Loans onboarding fallback.',
                'risk_level' => 'medium',
                'max_loan_amount' => $product->max_amount,
                'max_loan_tenure_months' => $product->tenure_months,
                'is_active' => true,
            ]
        );
    }
}

