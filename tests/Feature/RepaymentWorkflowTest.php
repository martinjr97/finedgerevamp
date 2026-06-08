<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\CashRegister;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Services\CustomerLifetimeStatementService;
use App\Services\LoanRepaymentRefundService;
use App\Support\RepaymentRecoveryMethod;
use App\Services\CashRegisterService;
use App\Services\LoanRepaymentLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RepaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Repayment Co '.$suffix,
            'slug' => 'repayment-co-'.$suffix,
            'code' => 'R'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeLoanProduct(Company $company, string $suffix): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Repayment Product '.$suffix,
            'code' => 'RP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function makeCustomer(Company $company, LoanProduct $loanProduct, string $suffix): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Repayment',
            'last_name' => 'Customer',
            'email' => 'repayment-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
    }

    private function makeLoan(
        Customer $customer,
        LoanProduct $loanProduct,
        Channel $channel,
        float $outstanding = 1000,
        ?string $dueDate = null
    ): Loan
    {
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $loanProduct->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($loanProduct),
            'principal_amount' => $outstanding,
            'processing_fee' => 0,
            'total_amount' => $outstanding,
            'amount_paid' => 0,
            'outstanding_balance' => $outstanding,
            'tenure_months' => 1,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->subDays(5)->toDateString(),
            'last_payment_date' => now()->addDays(25)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => $dueDate ?? now()->addDays(15)->toDateString(),
            'expected_amount' => $outstanding,
            'amount_paid' => 0,
            'remaining_amount' => $outstanding,
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
            'first_name' => 'Repay',
            'last_name' => 'Approver',
            'email' => 'repay-admin-'.$suffix.'@example.com',
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

    public function test_customer_submission_uses_pending_status_for_non_integrated_channel(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Cash '.$suffix,
            'code' => 'CASH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 1500);

        $response = $this->actingAs($customer, 'customer')
            ->withSession([
                'repayment.type' => 'full',
                'repayment.channel_id' => $channel->id,
                'repayment.phone_number' => $customer->phone,
            ])
            ->post(route('customer.repayments.process'));

        $response->assertRedirect(route('customer.repayments.success'));

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $this->assertSame('pending', $repayment->status);
        $this->assertDatabaseCount('loan_repayments', 0);

        $loan->refresh();
        $this->assertSame(1500.0, (float) $loan->outstanding_balance);
        $this->assertSame(0.0, (float) $loan->amount_paid);
    }

    public function test_admin_can_approve_pending_repayment_and_apply_to_loan(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Manual Channel '.$suffix,
            'code' => 'MAN-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 1000);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 300,
            'phone_number' => $customer->phone,
            'status' => 'pending',
            'metadata' => [
                'repayment_type' => 'full',
                'submitted_from' => 'customer_portal',
            ],
        ]);

        $bank = Bank::create([
            'name' => 'Branch Cash Account '.$suffix,
            'account_number' => 'ACC-'.$suffix,
            'account_name' => 'Repayment Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $admin = $this->makeAdminWithPermissions(['repayments.approve', 'repayments.view']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.approve', $repayment), [
                'channel_id' => $channel->id,
                'manual_source' => 'bank',
                'bank_id' => $bank->id,
                'notes' => 'Verified cash repayment at branch.',
            ]);

        $response->assertRedirect(route('admin.repayments.show', $repayment));

        $repayment->refresh();
        $this->assertSame('completed', $repayment->status);
        $this->assertNotNull($repayment->processed_at);

        $loan->refresh();
        $this->assertSame(300.0, (float) $loan->amount_paid);
        $this->assertSame(700.0, (float) $loan->outstanding_balance);

        $this->assertDatabaseHas('loan_repayments', [
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'amount' => 300.00,
        ]);
    }

    public function test_admin_submission_for_non_integrated_channel_defaults_to_pending(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Cash Manual '.$suffix,
            'code' => 'CASHMAN-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $this->makeLoan($customer, $loanProduct, $channel, 1200);

        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'full',
                'channel_id' => $channel->id,
                'phone_number' => $customer->phone,
                'submission_mode' => 'auto',
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
                'notes' => 'Cash received over counter.',
            ]);

        $repayment = Repayment::query()->latest('id')->first();

        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $this->assertSame('pending', $repayment->status);
        $this->assertSame('manual', $repayment->metadata['submission_mode'] ?? null);
    }

    public function test_partial_all_loans_uses_nearest_due_date_priority(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Integrated Wallet '.$suffix,
            'code' => 'INT-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $nearestDueLoan = $this->makeLoan($customer, $loanProduct, $channel, 2000, now()->addDays(5)->toDateString());
        $laterDueLoan = $this->makeLoan($customer, $loanProduct, $channel, 2000, now()->addDays(20)->toDateString());

        $response = $this->actingAs($customer, 'customer')
            ->withSession([
                'repayment.type' => 'partial',
                'repayment.loan_id' => null,
                'repayment.amount' => 2500,
                'repayment.channel_id' => $channel->id,
                'repayment.phone_number' => $customer->phone,
            ])
            ->post(route('customer.repayments.process'));

        $response->assertRedirect(route('customer.repayments.success'));

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $this->assertSame('processing', $repayment->status);

        $admin = $this->makeAdminWithPermissions(['repayments.process', 'repayments.view']);

        $providerUpdateResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.processing-status', $repayment), [
                'provider_status' => 'success',
                'provider_message' => 'Provider confirmed payment deduction.',
                'external_reference' => 'EXT-TEST-'.$suffix,
                'external_transaction_id' => 'TXN-TEST-'.$suffix,
            ]);

        $providerUpdateResponse->assertRedirect(route('admin.repayments.show', $repayment));

        $nearestDueLoan->refresh();
        $laterDueLoan->refresh();

        $this->assertSame(2000.0, (float) $nearestDueLoan->amount_paid);
        $this->assertSame(0.0, (float) $nearestDueLoan->outstanding_balance);
        $this->assertSame(500.0, (float) $laterDueLoan->amount_paid);
        $this->assertSame(1500.0, (float) $laterDueLoan->outstanding_balance);

        $repayment->refresh();
        $this->assertSame('completed', $repayment->status);
    }

    public function test_repayment_create_page_preselects_loan_when_loan_id_query_is_present(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 1500);

        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.repayments.create', [
                'customer' => $customer,
                'loan_id' => $loan->id,
            ]));

        $response->assertOk();
        $response->assertSee('Recording repayment for loan');
        $response->assertSee($loan->loan_number);
        $response->assertSee('Back to Loan');
        $response->assertViewHas('preselectedLoan', fn (Loan $selected): bool => $selected->id === $loan->id);
        $response->assertViewHas('returnToLoanUrl', route('admin.loans.show', $loan));
    }

    public function test_repayment_create_page_without_loan_id_keeps_customer_flow(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP2-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $this->makeLoan($customer, $loanProduct, $channel, 800);

        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.repayments.create', $customer));

        $response->assertOk();
        $response->assertDontSee('Recording repayment for loan');
        $response->assertDontSee('Back to Loan');
        $response->assertViewHas('preselectedLoan', null);
        $response->assertViewHas('returnToLoanUrl', null);
    }

    public function test_loan_show_page_links_to_repayment_create_with_loan_preselected(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP3-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 950);

        $admin = $this->makeAdminWithPermissions(['repayments.create', 'loans.view']);

        $expectedUrl = route('admin.customers.repayments.create', [
            'customer' => $customer,
            'loan_id' => $loan->id,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan));

        $response->assertOk();
        $response->assertSee('Record Repayment');
        $response->assertSee($expectedUrl, false);
    }

    public function test_create_form_shows_outstanding_guidance_not_maximum_wording(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP4-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.approve']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.repayments.create', ['customer' => $customer, 'loan_id' => $loan->id]))
            ->assertOk()
            ->assertSee('Current outstanding balance', false)
            ->assertSee('customer credit/suspense', false)
            ->assertSee('Payment references', false)
            ->assertSee('Receipt / deposit reference', false)
            ->assertSee('Provider transaction ID', false)
            ->assertSee('phone_number_group', false)
            ->assertDontSee('Maximum for selected loan', false);
    }

    public function test_partial_repayment_accepts_amount_above_outstanding_with_overpayment_details(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP4-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);

        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.approve']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
                'amount' => 800,
                'channel_id' => $channel->id,
                'submission_mode' => 'manual',
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
                'overpayment_reason' => 'Customer paid extra',
                'overpayment_confirmed' => '1',
            ]);

        $repayment = Repayment::query()->latest('id')->first();
        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $this->assertSame(800.0, (float) $repayment->total_amount);
        $this->assertSame('Customer paid extra', $repayment->metadata['overpayment']['reason'] ?? null);

        $bank = Bank::create([
            'name' => 'Overpay Bank '.$suffix,
            'account_number' => 'OP-'.$suffix,
            'account_name' => 'Repayment Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.repayments.approve', $repayment), [
                'channel_id' => $channel->id,
                'manual_source' => 'bank',
                'bank_id' => $bank->id,
            ]);

        $loan->refresh();
        $this->assertSame(800.0, (float) $loan->amount_paid);
        $this->assertSame(0.0, (float) $loan->outstanding_balance);
    }

    public function test_overpayment_above_outstanding_but_below_settlement_is_advance_not_suspense(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Advance Channel '.$suffix,
            'code' => 'ADV-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 1000);
        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.approve']);
        $bank = Bank::create([
            'name' => 'Advance Bank '.$suffix,
            'account_number' => 'ADV-B-'.$suffix,
            'account_name' => 'Repayment Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 600,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            'manual_source' => 'cash',
            'overpayment_reason' => 'Customer paid extra',
            'overpayment_confirmed' => '1',
        ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $this->actingAs($admin, 'admin')->post(route('admin.repayments.approve', $repayment), [
            'channel_id' => $channel->id,
            'manual_source' => 'bank',
            'bank_id' => $bank->id,
        ]);

        $loan->refresh();
        $ledger = app(LoanRepaymentLedgerService::class);
        $this->assertSame(600.0, (float) $loan->amount_paid);
        $this->assertSame(400.0, (float) $loan->outstanding_balance);
        $this->assertSame(0.0, $ledger->calculateSuspenseAmount($loan));
    }

    public function test_overpayment_creates_suspense_only_above_expected_settlement(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Suspense Channel '.$suffix,
            'code' => 'SUS-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.approve']);
        $bank = Bank::create([
            'name' => 'Suspense Bank '.$suffix,
            'account_number' => 'SUS-B-'.$suffix,
            'account_name' => 'Repayment Co',
            'bank_name' => 'Test Bank',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 700,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            'manual_source' => 'cash',
            'overpayment_reason' => 'Gateway over-collected',
            'overpayment_confirmed' => '1',
        ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $this->actingAs($admin, 'admin')->post(route('admin.repayments.approve', $repayment), [
            'channel_id' => $channel->id,
            'manual_source' => 'bank',
            'bank_id' => $bank->id,
        ]);

        $loan->refresh();
        $ledger = app(LoanRepaymentLedgerService::class);
        $this->assertSame(700.0, (float) $loan->amount_paid);
        $this->assertSame(0.0, (float) $loan->outstanding_balance);
        $this->assertSame(200.0, $ledger->calculateSuspenseAmount($loan));
    }

    public function test_overpayment_requires_reason_when_amount_exceeds_outstanding(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP4b-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 400);
        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.customers.repayments.create', ['customer' => $customer]))
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
                'amount' => 600,
                'channel_id' => $channel->id,
                'submission_mode' => 'manual',
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ])
            ->assertRedirect(route('admin.customers.repayments.create', $customer))
            ->assertSessionHasErrors(['overpayment_reason', 'overpayment_confirmed']);

        $this->assertDatabaseCount('repayments', 0);
    }

    public function test_partial_repayment_accepts_amount_up_to_loan_outstanding(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP5-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);

        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
                'amount' => 250,
                'channel_id' => $channel->id,
                'submission_mode' => 'manual',
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ]);

        $repayment = Repayment::query()->latest('id')->first();

        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $this->assertSame(250.0, (float) $repayment->total_amount);
    }

    public function test_full_repayment_uses_total_active_outstanding_not_submitted_amount(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);

        $channel = Channel::create([
            'name' => 'Repay Channel '.$suffix,
            'code' => 'REP6-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $this->makeLoan($customer, $loanProduct, $channel, 400);
        $this->makeLoan($customer, $loanProduct, $channel, 600);

        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'full',
                'amount' => 99999,
                'channel_id' => $channel->id,
                'submission_mode' => 'manual',
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'cash',
            ]);

        $repayment = Repayment::query()->latest('id')->first();

        $this->assertNotNull($repayment);
        $response->assertRedirect(route('admin.repayments.show', $repayment));
        $this->assertSame(1000.0, (float) $repayment->total_amount);
    }

    public function test_cash_repayment_approval_credits_cash_register_for_balance_sheet(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Cash Register Channel '.$suffix,
            'code' => 'CRC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 800);
        $admin = $this->makeAdminWithPermissions([
            'repayments.create',
            'repayments.approve',
            'financial-statements.view',
        ]);

        $cashRegister = app(CashRegisterService::class)->defaultRegister();

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 250,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::NORMAL,
            'manual_source' => 'cash',
        ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $this->assertSame('cash', $repayment->metadata['manual_source'] ?? null);

        $this->actingAs($admin, 'admin')->post(route('admin.repayments.approve', $repayment), [
            'channel_id' => $channel->id,
            'manual_source' => 'cash',
        ]);

        $repayment->refresh();
        $cashRegister->refresh();

        $this->assertSame('completed', $repayment->status);
        $this->assertSame('cash', $repayment->received_via_type);
        $this->assertSame($cashRegister->id, (int) $repayment->received_via_id);
        $this->assertSame(250.0, (float) $cashRegister->current_balance);

        $balanceSheet = $this->actingAs($admin, 'admin')
            ->get(route('admin.financial-statements.balance-sheet', [
                'as_of_date' => now()->toDateString(),
            ]));

        $balanceSheet->assertOk();
        $balanceSheet->assertSee('Cash on Hand', false);
        $balanceSheet->assertSee(number_format(250, 2), false);
    }

    public function test_manual_source_other_is_rejected_on_repayment_create(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Reject Other '.$suffix,
            'code' => 'RO-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.customers.repayments.create', ['customer' => $customer, 'loan_id' => $loan->id]))
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
                'amount' => 100,
                'channel_id' => $channel->id,
                'submission_mode' => 'manual',
                'recovery_method' => RepaymentRecoveryMethod::NORMAL,
                'manual_source' => 'other',
            ])
            ->assertSessionHasErrors('manual_source');

        $this->assertDatabaseCount('repayments', 0);
    }

    public function test_repayment_defaults_to_normal_recovery_method(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Default Recovery '.$suffix,
            'code' => 'DR-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);

        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => 100,
            'status' => 'pending',
        ]);

        $this->assertSame(RepaymentRecoveryMethod::NORMAL, $repayment->fresh()->recovery_method);
    }

    public function test_admin_can_select_litigation_recovery_method(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Litigation '.$suffix,
            'code' => 'LIT-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.approve']);

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 200,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::LITIGATION,
            'manual_source' => 'cash',
        ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $this->assertSame(RepaymentRecoveryMethod::LITIGATION, $repayment->recovery_method);

        $this->actingAs($admin, 'admin')->post(route('admin.repayments.approve', $repayment), [
            'channel_id' => $channel->id,
            'manual_source' => 'cash',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan));
        $response->assertOk();
        $response->assertSee('Litigation', false);
    }

    public function test_invalid_recovery_method_is_rejected(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Invalid Recovery '.$suffix,
            'code' => 'IR-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.customers.repayments.create', ['customer' => $customer, 'loan_id' => $loan->id]))
            ->post(route('admin.customers.repayments.store', $customer), [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
                'amount' => 100,
                'channel_id' => $channel->id,
                'submission_mode' => 'manual',
                'recovery_method' => 'court_order',
                'manual_source' => 'cash',
            ])
            ->assertSessionHasErrors('recovery_method');

        $this->assertDatabaseCount('repayments', 0);
    }

    public function test_create_form_lists_all_recovery_method_options(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Form Options '.$suffix,
            'code' => 'FO-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.repayments.create', $customer));

        $response->assertOk();
        $response->assertSee('Recovery Method', false);
        $response->assertSee('value="'.RepaymentRecoveryMethod::NORMAL.'"', false);
        $response->assertSee('Payroll Deduction', false);
        $response->assertSee('Collateral Recovery', false);
        $response->assertSee('Settlement Agreement', false);
    }

    public function test_payroll_deduction_recovery_method_shows_on_admin_repayment_index(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Payroll '.$suffix,
            'code' => 'PR-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.view']);

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 150,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::PAYROLL_DEDUCTION,
            'manual_source' => 'cash',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.repayments.index'));

        $response->assertOk();
        $response->assertSee('Payroll Deduction', false);
    }

    public function test_statement_shows_recovery_in_notes_not_as_transaction_type(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Statement '.$suffix,
            'code' => 'ST-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions(['repayments.create', 'repayments.approve']);

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 200,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::LITIGATION,
            'manual_source' => 'cash',
        ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $this->actingAs($admin, 'admin')->post(route('admin.repayments.approve', $repayment), [
            'channel_id' => $channel->id,
            'manual_source' => 'cash',
        ]);

        $statement = app(CustomerLifetimeStatementService::class)->build($customer->fresh(), loanId: $loan->id);
        $paymentRow = $statement['rows']->firstWhere('transaction_type', 'payment');

        $this->assertNotNull($paymentRow);
        $this->assertSame('Repayment received', $paymentRow['description']);
        $this->assertSame('payment', $paymentRow['transaction_type']);
        $this->assertStringContainsString('Recovery method: Litigation', (string) ($paymentRow['notes'] ?? ''));
    }

    public function test_refund_inherits_recovery_method_but_remains_refund_transaction_type(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $channel = Channel::create([
            'name' => 'Refund Recovery '.$suffix,
            'code' => 'RR-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $loan = $this->makeLoan($customer, $loanProduct, $channel, 500);
        $admin = $this->makeAdminWithPermissions([
            'repayments.create',
            'repayments.approve',
            'repayments.refund',
            'loans.view',
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.customers.repayments.store', $customer), [
            'repayment_type' => 'partial',
            'loan_id' => $loan->id,
            'amount' => 300,
            'channel_id' => $channel->id,
            'submission_mode' => 'manual',
            'recovery_method' => RepaymentRecoveryMethod::LITIGATION,
            'manual_source' => 'cash',
        ]);

        $repayment = Repayment::query()->latest('id')->firstOrFail();
        $this->actingAs($admin, 'admin')->post(route('admin.repayments.approve', $repayment), [
            'channel_id' => $channel->id,
            'manual_source' => 'cash',
        ]);

        $payment = LoanRepayment::query()
            ->where('loan_id', $loan->id)
            ->where('transaction_type', LoanRepayment::TRANSACTION_TYPE_PAYMENT)
            ->firstOrFail();

        $this->actingAs($admin, 'admin')->post(route('admin.loans.refund', $loan), [
            'loan_repayment_id' => $payment->id,
            'amount' => 100,
            'reason' => 'Partial refund after litigation recovery',
        ]);

        $refundLoanRepayment = LoanRepayment::query()
            ->where('transaction_type', LoanRepayment::TRANSACTION_TYPE_REFUND)
            ->firstOrFail();

        $this->assertSame(RepaymentRecoveryMethod::LITIGATION, $refundLoanRepayment->repayment->recovery_method);
        $this->assertTrue($refundLoanRepayment->isRefund());

        $statement = app(CustomerLifetimeStatementService::class)->build($customer->fresh(), loanId: $loan->id);
        $refundRow = $statement['rows']->firstWhere('transaction_type', 'refund');

        $this->assertNotNull($refundRow);
        $this->assertSame('refund', $refundRow['transaction_type']);
        $this->assertStringContainsString('Refund issued', $refundRow['description']);
        $this->assertStringContainsString('Partial refund after litigation recovery', (string) ($refundRow['notes'] ?? ''));

        $response = $this->actingAs($admin, 'admin')->get(route('admin.loans.show', $loan->fresh()));
        $response->assertOk();
        $response->assertSee('Refund', false);
        $response->assertSee('Litigation', false);
    }
}
