<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\LoanRateType;
use Illuminate\Database\Seeder;
use RuntimeException;

class EmployeeLoanProductSeeder extends Seeder
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
            throw new RuntimeException('Cannot seed Employee Loan product: no company record exists.');
        }

        $loanProduct = LoanProduct::query()->firstOrNew([
            'code' => 'EMPLOYEE_LOAN',
        ]);

        $loanProduct->fill([
            'company_id' => $loanProduct->exists ? $loanProduct->company_id : $company->id,
            'name' => 'Employee Loan',
            'category' => 'employee',
            'description' => 'Internal HR staff loan product. Not offered to retail customers.',
            'tenure_months' => 12,
            'max_amount' => 500_000,
            'requires_collateral' => false,
            'requires_reference' => false,
            'is_active' => true,
            'is_public_on_website' => false,
            'rules' => [
                'employee_loan_only' => true,
            ],
        ]);
        $loanProduct->save();

        LoanRateType::query()->updateOrCreate(
            ['code' => 'EMPLOYEE_RATE'],
            [
                'loan_product_id' => $loanProduct->id,
                'name' => 'Employee Rate',
                'description' => 'Rate plan for HR employee loans. Configure tenure rows under Loan Rates.',
                'accrual_period' => 'daily',
                'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
                'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
                'is_active' => true,
                'is_public_on_website' => false,
            ]
        );
    }
}
