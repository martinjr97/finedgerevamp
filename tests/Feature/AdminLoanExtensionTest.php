<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanExtensionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminLoanExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Extension Co '.$suffix,
            'slug' => 'extension-co-'.$suffix,
            'code' => 'EXT'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeLoanProduct(Company $company, string $suffix): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Extension Product '.$suffix,
            'code' => 'EP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function makeCustomer(Company $company, LoanProduct $loanProduct, string $suffix): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Extend',
            'last_name' => 'Customer',
            'email' => 'extend-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
    }

    private function makeLoan(Customer $customer, LoanProduct $loanProduct, Channel $channel, float $outstanding = 1200): Loan
    {
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $loanProduct->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($loanProduct),
            'principal_amount' => $outstanding,
            'processing_fee' => 0,
            'daily_rate' => 0.001,
            'total_amount' => $outstanding,
            'amount_paid' => 0,
            'outstanding_balance' => $outstanding,
            'tenure_months' => 2,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->addDays(5)->toDateString(),
            'last_payment_date' => now()->addDays(35)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'expected_amount' => 600,
            'amount_paid' => 0,
            'remaining_amount' => 600,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 2,
            'due_date' => now()->addDays(35)->toDateString(),
            'expected_amount' => 600,
            'amount_paid' => 0,
            'remaining_amount' => 600,
            'status' => 'upcoming',
            'days_overdue' => 0,
        ]);

        return $loan;
    }

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany('admin-'.$suffix);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Loan',
            'last_name' => 'Extender',
            'email' => 'loan-ext-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_admin_without_extension_permission_cannot_extend_loan(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Ext Channel '.$suffix,
            'code' => 'EC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel);
        $admin = $this->makeAdminWithPermissions(['loans.view']);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.extend', $loan), [
            'extension_type' => 1,
            'extension_period_value' => 30,
            'extension_period_unit' => 'days',
            'interest_mode' => 3,
            'interest_value' => 100,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('loan_extensions', 0);
    }

    public function test_due_date_extension_updates_unpaid_schedule_and_records_history(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Ext Channel '.$suffix,
            'code' => 'EC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel);
        $admin = $this->makeAdminWithPermissions(['loan.extend', 'loans.view']);

        $oldLastDueDate = $loan->paymentSchedules()->orderByDesc('due_date')->value('due_date');

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.extend', $loan), [
            'extension_type' => 1,
            'extension_period_value' => 30,
            'extension_period_unit' => 'days',
            'interest_mode' => 3,
            'interest_value' => 200,
            'notes' => 'Customer requested extension due to cashflow timing.',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));

        $loan->refresh();
        $latestSchedule = $loan->paymentSchedules()->orderByDesc('due_date')->first();
        $remainingAmounts = $loan->paymentSchedules()->pluck('remaining_amount')->map(fn ($amount) => (float) $amount)->all();

        $this->assertNotNull($latestSchedule);
        $this->assertSame(1400.0, (float) $loan->outstanding_balance);
        $this->assertContains(800.0, $remainingAmounts);
        $this->assertSame(
            Carbon::parse($oldLastDueDate)->addDays(30)->toDateString(),
            $latestSchedule->due_date->toDateString()
        );

        $this->assertDatabaseHas('loan_extensions', [
            'loan_id' => $loan->id,
            'extension_type' => 1,
            'interest_mode' => 3,
            'interest_amount' => 200.00,
            'created_by' => $admin->id,
        ]);
    }

    public function test_extension_preview_uses_derived_daily_rate_from_rate_card(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Term Rate '.$suffix,
            'code' => 'TR_'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 0,
            'term_interest_percentage' => 30,
            'derived_daily_rate' => 0.001,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $channel = Channel::create([
            'name' => 'Ext Channel '.$suffix,
            'code' => 'EC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel, 1000);
        $loan->update([
            'loan_rate_id' => $loanRate->id,
            'daily_rate' => null,
            'principal_amount' => 1000,
            'outstanding_balance' => 1000,
        ]);

        $admin = $this->makeAdminWithPermissions(['loan.extend']);

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.loans.extend.preview', $loan), [
            'extension_type' => 1,
            'extension_period_value' => 30,
            'extension_period_unit' => 'days',
            'interest_mode' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $this->assertSame(30.0, (float) $response->json('interest.interest_amount'));
        $response->assertJsonPath('configured_rates.source', 'loan_rate');
        $this->assertSame(1030.0, (float) $response->json('projected.projected_outstanding'));
    }

    public function test_extension_preview_endpoint_validates_interest_value_for_custom_mode(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Ext Channel '.$suffix,
            'code' => 'EC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel);
        $admin = $this->makeAdminWithPermissions(['loan.extend']);

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.loans.extend.preview', $loan), [
            'extension_type' => 1,
            'extension_period_value' => 7,
            'extension_period_unit' => 'days',
            'interest_mode' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Interest value is required for the selected interest mode.');
    }

    public function test_configured_rate_extension_uses_loan_snapshot_daily_rate(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Ext Channel '.$suffix,
            'code' => 'EC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel, 2000);
        $loan->update(['daily_rate' => 0.002, 'principal_amount' => 2000, 'outstanding_balance' => 2000]);

        $preview = app(LoanExtensionService::class)->preview($loan, [
            'extension_type' => 1,
            'extension_period_value' => 10,
            'extension_period_unit' => 'days',
            'interest_mode' => 1,
        ]);

        $this->assertTrue($preview['eligible']);
        $this->assertSame(40.0, $preview['interest']['interest_amount']);
        $this->assertSame('loan_snapshot', $preview['configured_rates']['source']);
    }

    public function test_restructure_marks_old_installments_and_hides_them_from_default_schedule_queries(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $product = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Ext Channel '.$suffix,
            'code' => 'EC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $product, $suffix);
        $loan = $this->makeLoan($customer, $product, $channel);
        $admin = $this->makeAdminWithPermissions(['loan.extend', 'loans.view']);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loans.extend', $loan), [
            'extension_type' => 3,
            'extension_period_value' => 1,
            'extension_period_unit' => 'months',
            'interest_mode' => 3,
            'interest_value' => 100,
            'new_installment_count' => 3,
            'notes' => 'Restructured after review.',
        ]);

        $response->assertRedirect(route('admin.loans.show', $loan));

        $visibleSchedules = LoanPaymentSchedule::query()->where('loan_id', $loan->id)->get();
        $allSchedules = LoanPaymentSchedule::withoutGlobalScope('non_restructured')->where('loan_id', $loan->id)->get();

        $this->assertCount(3, $visibleSchedules);
        $this->assertCount(5, $allSchedules);
        $this->assertSame(2, $allSchedules->where('is_restructured', true)->count());
    }
}
