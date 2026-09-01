<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCustomerCashflowStatsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Cashflow Co '.$suffix,
            'slug' => 'cashflow-co-'.$suffix,
            'code' => 'CF'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Cashflow',
            'last_name' => 'Admin',
            'email' => 'cashflow-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('customers.view');

        return $admin;
    }

    private function makeProduct(Admin $admin): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Cashflow',
            'code' => 'CFC-'.Str::lower(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function makeCustomer(LoanProduct $product, string $suffix): Customer
    {
        return Customer::create([
            'company_id' => $product->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Customer',
            'last_name' => strtoupper($suffix),
            'email' => 'customer-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
    }

    private function makeActiveLoan(Customer $customer, LoanProduct $product, float $principal, Carbon $disbursedAt): Loan
    {
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => $principal,
            'processing_fee' => 0,
            'total_amount' => $principal,
            'amount_paid' => 0,
            'outstanding_balance' => $principal,
            'tenure_months' => 1,
            'loan_start_date' => $disbursedAt->toDateString(),
            'loan_end_date' => $disbursedAt->copy()->addMonth()->toDateString(),
            'first_payment_date' => $disbursedAt->copy()->addMonth()->toDateString(),
            'last_payment_date' => $disbursedAt->copy()->addMonth()->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => $disbursedAt,
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => $disbursedAt->copy()->subDays(10)->toDateString(),
            'expected_amount' => $principal,
            'amount_paid' => 0,
            'remaining_amount' => $principal,
            'status' => 'overdue',
            'days_overdue' => 10,
        ]);

        return $loan;
    }

    public function test_customer_show_cashflow_stats_are_scoped_to_that_customer_only(): void
    {
        Carbon::setTestNow('2026-08-31 12:00:00');

        $admin = $this->makeAdmin();
        $product = $this->makeProduct($admin);

        $subject = $this->makeCustomer($product, 'subject');
        $other = $this->makeCustomer($product, 'other');

        $this->makeActiveLoan($subject, $product, 1000.00, now()->subMonths(1));
        $this->makeActiveLoan($other, $product, 9000.00, now()->subMonths(1));

        $subjectRepayment = Repayment::create([
            'customer_id' => $subject->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 250.00,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $otherRepayment = Repayment::create([
            'customer_id' => $other->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 7500.00,
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        LoanRepayment::create([
            'repayment_id' => $subjectRepayment->id,
            'loan_id' => $subject->loans()->first()->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => 250.00,
            'principal_amount' => 250.00,
            'interest_amount' => 0,
            'processing_fee_amount' => 0,
            'outstanding_balance_before' => 1000.00,
            'outstanding_balance_after' => 750.00,
        ]);

        LoanRepayment::create([
            'repayment_id' => $otherRepayment->id,
            'loan_id' => $other->loans()->first()->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => 7500.00,
            'principal_amount' => 7500.00,
            'interest_amount' => 0,
            'processing_fee_amount' => 0,
            'outstanding_balance_before' => 9000.00,
            'outstanding_balance_after' => 1500.00,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $subject));

        $response->assertOk();
        $response->assertViewHas('customerCashflowStats', function (array $stats) {
            $this->assertSame(1000.0, $stats['disbursements']['total']);
            $this->assertSame(250.0, $stats['repayments']['total']);
            $this->assertSame(1000.0, $stats['portfolio']['total']);

            return true;
        });

        $response->assertSee('not the whole loan book', false);
        $response->assertSee('Outstanding (active loans)', false);
        $response->assertDontSee('ZMW 9,000.00', false);
        $response->assertDontSee('ZMW 7,500.00', false);

        Carbon::setTestNow();
    }
}
