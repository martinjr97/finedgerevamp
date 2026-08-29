<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_loan_number_uses_fin_prefix_and_table_id(): void
    {
        $company = Company::create([
            'name' => 'Loan Number Co',
            'slug' => 'loan-number-co',
            'code' => 'LNC',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Government Payroll',
            'code' => 'GOV-001',
            'category' => 'government',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Loan',
            'last_name' => 'Customer',
            'email' => 'loan-number@example.com',
            'phone' => '260955000099',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 1,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'pending_approval',
        ]);

        $expected = Loan::formatLoanNumber($product, $loan->id);

        $this->assertSame($expected, $loan->loan_number);
        $this->assertStringStartsWith('FIN-GOV-001-', $loan->loan_number);
        $this->assertStringEndsWith('-'.$loan->id, $loan->loan_number);
        $this->assertStringNotContainsString('LN-', $loan->loan_number);
    }

    public function test_explicit_legacy_style_loan_number_is_not_overwritten(): void
    {
        $company = Company::create([
            'name' => 'Legacy Loan Co',
            'slug' => 'legacy-loan-co-'.Str::random(4),
            'code' => 'LLC'.Str::upper(Str::random(2)),
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Legacy Product',
            'code' => 'LEG-PRD',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Legacy',
            'last_name' => 'Borrower',
            'email' => 'legacy-loan@example.com',
            'phone' => '260955000088',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $legacyNumber = 'LN-LEG-PRD-20260101-9999';

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => $legacyNumber,
            'principal_amount' => 500,
            'processing_fee' => 0,
            'total_amount' => 500,
            'amount_paid' => 0,
            'outstanding_balance' => 500,
            'tenure_months' => 1,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'pending_approval',
        ]);

        $this->assertSame($legacyNumber, $loan->loan_number);
    }
}
