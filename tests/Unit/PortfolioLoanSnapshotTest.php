<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Support\PortfolioLoanSnapshot;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortfolioLoanSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_exposure_never_exceeds_total_outstanding_balance(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Snapshot Co '.$suffix,
            'slug' => 'snapshot-co-'.$suffix,
            'code' => 'SN'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'MOU Product',
            'code' => 'MOU-'.$suffix,
            'category' => 'mou',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Borrower',
            'last_name' => 'One',
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'SN-'.$suffix,
            'principal_amount' => 10000,
            'processing_fee' => 0,
            'total_amount' => 10000,
            'amount_paid' => 3412,
            'outstanding_balance' => 6588,
            'tenure_months' => 3,
            'loan_start_date' => now()->subMonths(4)->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subMonths(3)->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(4),
        ]);

        // Stale schedule rows can sum above the loan's booked outstanding after migration.
        foreach ([3000.0, 3000.0, 2389.0] as $index => $remaining) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $index + 1,
                'due_date' => now()->subMonths(3 - $index)->toDateString(),
                'expected_amount' => $remaining,
                'amount_paid' => 0,
                'remaining_amount' => $remaining,
                'status' => 'overdue',
                'days_overdue' => 30,
            ]);
        }

        $snapshot = PortfolioLoanSnapshot::forCompany($company);

        $this->assertSame(6588.0, $snapshot['total_outstanding_balance']);
        $this->assertSame(6588.0, $snapshot['total_overdue_amount']);
        $this->assertLessThanOrEqual(
            $snapshot['total_outstanding_balance'],
            $snapshot['total_overdue_amount']
        );

        Carbon::setTestNow();
    }

    public function test_partial_overdue_is_subset_of_outstanding(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Partial Co '.$suffix,
            'slug' => 'partial-co-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character',
            'code' => 'CHR-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Borrower',
            'last_name' => 'Two',
            'email' => 'borrower2-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'PC-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 100,
            'outstanding_balance' => 900,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subDays(10)->toDateString(),
            'last_payment_date' => now()->addDays(20)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => now()->subDays(3)->toDateString(),
            'expected_amount' => 250,
            'amount_paid' => 0,
            'remaining_amount' => 250,
            'status' => 'overdue',
            'days_overdue' => 3,
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 2,
            'due_date' => now()->addDays(20)->toDateString(),
            'expected_amount' => 650,
            'amount_paid' => 0,
            'remaining_amount' => 650,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);

        $snapshot = PortfolioLoanSnapshot::forCompany($company);

        $this->assertSame(900.0, $snapshot['total_outstanding_balance']);
        $this->assertSame(250.0, $snapshot['total_overdue_amount']);
        $this->assertTrue($snapshot['total_overdue_amount'] < $snapshot['total_outstanding_balance']);

        Carbon::setTestNow();
    }

    public function test_bulk_outstanding_balances_match_snapshot_totals(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Bulk Co '.$suffix,
            'slug' => 'bulk-co-'.$suffix,
            'code' => 'BK'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Group Product',
            'code' => 'GRP-'.$suffix,
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $group = \App\Models\CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Bulk Group',
            'code' => 'BG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'customer_group_id' => $group->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Bulk',
            'last_name' => 'Borrower',
            'email' => 'bulk-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'BK-'.$suffix,
            'principal_amount' => 5000,
            'processing_fee' => 0,
            'total_amount' => 5000,
            'amount_paid' => 1000,
            'outstanding_balance' => 4000,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subDays(10)->toDateString(),
            'last_payment_date' => now()->addDays(20)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        $companySnapshot = PortfolioLoanSnapshot::forCompany($company);
        $groupSnapshot = PortfolioLoanSnapshot::forCustomerGroup($group);
        $companyBulk = PortfolioLoanSnapshot::outstandingBalancesForCompanies([$company->id]);
        $groupBulk = PortfolioLoanSnapshot::outstandingBalancesForCustomerGroups([$group->id]);

        $this->assertSame($companySnapshot['total_outstanding_balance'], $companyBulk[$company->id]);
        $this->assertSame($groupSnapshot['total_outstanding_balance'], $groupBulk[$group->id]);
        $this->assertSame(4000.0, $companyBulk[$company->id]);

        Carbon::setTestNow();
    }
}
