<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanBookReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: Admin, customer: Customer, product: LoanProduct}
     */
    private function makeContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Loan Book Co '.$suffix,
            'slug' => 'loan-book-co-'.$suffix,
            'code' => 'LBC-'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Loan',
            'last_name' => 'Book',
            'email' => 'loan-book-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('reports.view');

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Loan Book Product',
            'code' => 'LB-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Book',
            'last_name' => 'Customer',
            'email' => 'loan-book-customer-'.$suffix.'@example.com',
            'phone' => '26097'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        return compact('admin', 'customer', 'product');
    }

    private function makeLoan(Customer $customer, LoanProduct $product, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(10)),
            'principal_amount' => 5000,
            'processing_fee' => 250,
            'interest_accrued' => 300,
            'total_amount' => 5550,
            'amount_paid' => 0,
            'outstanding_balance' => 5550,
            'tenure_months' => 6,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(6)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'approved',
            'disbursement_status' => 'pending',
        ], $overrides));
    }

    public function test_loan_book_defaults_to_active_disbursed_portfolio(): void
    {
        $context = $this->makeContext();

        $pendingDisbursement = $this->makeLoan($context['customer'], $context['product'], [
            'loan_number' => 'LN-PENDING-BOOK',
            'status' => 'approved',
            'disbursement_status' => 'pending',
            'outstanding_balance' => 9000,
        ]);

        $activeLoan = $this->makeLoan($context['customer'], $context['product'], [
            'loan_number' => 'LN-ACTIVE-BOOK',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
            'outstanding_balance' => 3200,
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.reports.loan-book'));

        $response->assertOk();
        $response->assertSee('Active Loans');
        $response->assertSee('Active Portfolio Outstanding');
        $response->assertSee($activeLoan->loan_number);
        $response->assertDontSee($pendingDisbursement->loan_number);
        $response->assertViewHas('stats', function (array $stats): bool {
            return (int) $stats['active_loans'] === 1
                && (int) $stats['total_loans'] === 2
                && (float) $stats['total_outstanding'] === 3200.0
                && (float) $stats['active_principal'] === 5000.0;
        });
    }

    public function test_loan_book_syncs_approved_disbursed_loans_to_active_on_load(): void
    {
        $context = $this->makeContext();

        $staleLoan = $this->makeLoan($context['customer'], $context['product'], [
            'loan_number' => 'LN-STALE-ACTIVE',
            'status' => 'approved',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subDay(),
        ]);

        $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.reports.loan-book'))
            ->assertOk();

        $staleLoan->refresh();

        $this->assertSame('active', $staleLoan->status);
    }

    public function test_apply_disbursement_completed_sets_loan_active(): void
    {
        $context = $this->makeContext();

        $loan = $this->makeLoan($context['customer'], $context['product'], [
            'status' => 'approved',
            'disbursement_status' => 'pending',
        ]);

        $loan->applyDisbursementCompleted(now());
        $loan->save();

        $loan->refresh();

        $this->assertTrue($loan->isActive());
        $this->assertSame('active', $loan->status);
        $this->assertSame('completed', $loan->disbursement_status);
    }

    public function test_loan_book_show_all_includes_approved_pending_disbursement(): void
    {
        $context = $this->makeContext();

        $pendingDisbursement = $this->makeLoan($context['customer'], $context['product'], [
            'loan_number' => 'LN-SHOW-ALL-PENDING',
            'status' => 'approved',
            'disbursement_status' => 'pending',
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.reports.loan-book', ['show_all' => 1]));

        $response->assertOk();
        $response->assertSee($pendingDisbursement->loan_number);
    }
}
