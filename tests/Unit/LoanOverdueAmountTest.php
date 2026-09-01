<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanOverdueAmountTest extends TestCase
{
    use RefreshDatabase;

    private function makeLoanForCustomer(Customer $customer, LoanProduct $product, string $suffix): Loan
    {
        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'OD-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 1,
            'loan_start_date' => now()->subMonths(2)->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subMonth()->toDateString(),
            'last_payment_date' => now()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(2),
        ]);
    }

    public function test_get_overdue_amount_only_includes_schedules_for_the_same_loan(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Overdue Co '.$suffix,
            'slug' => 'overdue-co-'.$suffix,
            'code' => 'OD'.$suffix,
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

        $subjectCustomer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Subject',
            'last_name' => 'Borrower',
            'email' => 'subject-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $otherCustomer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Other',
            'last_name' => 'Borrower',
            'email' => 'other-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $subjectLoan = $this->makeLoanForCustomer($subjectCustomer, $product, $suffix.'-a');
        $otherLoan = $this->makeLoanForCustomer($otherCustomer, $product, $suffix.'-b');

        LoanPaymentSchedule::create([
            'loan_id' => $subjectLoan->id,
            'period_number' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'expected_amount' => 1000,
            'amount_paid' => 0,
            'remaining_amount' => 1000,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $otherLoan->id,
            'period_number' => 1,
            'due_date' => now()->subDays(30)->toDateString(),
            'expected_amount' => 5000,
            'amount_paid' => 0,
            'remaining_amount' => 5000,
            'status' => 'overdue',
            'days_overdue' => 30,
        ]);

        $this->assertSame(0.0, (float) $subjectLoan->fresh()->getOverdueAmount());
        $this->assertSame(5000.0, (float) $otherLoan->fresh()->getOverdueAmount());

        Carbon::setTestNow();
    }
}
