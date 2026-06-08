<?php

namespace Tests\Feature;

use App\Http\Controllers\Customer\StatementController;
use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Services\LoanRepaymentLedgerService;
use App\Services\LoanRepaymentRefundService;
use App\Services\RepaymentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanRepaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    private LoanRepaymentLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(LoanRepaymentLedgerService::class);
    }

    private function makeCompany(string $suffix): Company
    {
        return Company::create([
            'name' => 'Refund Co '.$suffix,
            'slug' => 'refund-co-'.$suffix,
            'code' => 'RF'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    private function makeLoanProduct(Company $company, string $suffix): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Refund Product '.$suffix,
            'code' => 'RFP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function makeCustomer(Company $company, LoanProduct $loanProduct, string $suffix): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Refund',
            'last_name' => 'Customer',
            'email' => 'refund-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);
    }

    private function makeChannel(string $suffix): Channel
    {
        return Channel::create([
            'name' => 'Refund Channel '.$suffix,
            'code' => 'RFC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array{expected: float, due_date?: string}>  $installments
     */
    private function makeLoanWithSchedules(
        Customer $customer,
        LoanProduct $loanProduct,
        Channel $channel,
        array $installments
    ): Loan {
        $totalExpected = array_sum(array_column($installments, 'expected'));

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $loanProduct->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($loanProduct),
            'principal_amount' => $totalExpected,
            'processing_fee' => 0,
            'total_amount' => $totalExpected,
            'amount_paid' => 0,
            'outstanding_balance' => $totalExpected,
            'tenure_months' => count($installments),
            'loan_start_date' => now()->subMonths(2)->toDateString(),
            'loan_end_date' => now()->addMonths(count($installments))->toDateString(),
            'first_payment_date' => now()->subMonth()->toDateString(),
            'last_payment_date' => now()->addMonths(count($installments))->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonths(2),
        ]);

        foreach ($installments as $index => $installment) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $index + 1,
                'due_date' => $installment['due_date'] ?? now()->addMonths($index)->toDateString(),
                'expected_amount' => $installment['expected'],
                'amount_paid' => 0,
                'remaining_amount' => $installment['expected'],
                'status' => 'upcoming',
                'days_overdue' => 0,
            ]);
        }

        return $loan;
    }

    private function applyPayment(Loan $loan, Customer $customer, Channel $channel, float $amount): LoanRepayment
    {
        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => $amount,
            'phone_number' => $customer->phone,
            'status' => 'completed',
            'processed_at' => now(),
            'metadata' => ['repayment_type' => 'partial', 'loan_id' => $loan->id],
        ]);

        app(RepaymentProcessingService::class)->applyRepaymentToLoans(
            $repayment,
            $customer,
            'partial',
            $loan->id,
            $amount,
            'Test payment'
        );

        return LoanRepayment::query()
            ->where('repayment_id', $repayment->id)
            ->where('loan_id', $loan->id)
            ->firstOrFail();
    }

    private function makeAdminWithRefundPermission(): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany('admin-'.$suffix);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Refund',
            'last_name' => 'Admin',
            'email' => 'refund-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'repayments.refund', 'guard_name' => 'admin']);
        $admin->givePermissionTo('repayments.refund');

        return $admin;
    }

    public function test_refund_after_three_months_paid_on_twelve_month_loan_keeps_loan_active(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);

        $installments = [];
        for ($i = 0; $i < 12; $i++) {
            $installments[] = ['expected' => 50, 'due_date' => now()->addMonths($i)->toDateString()];
        }

        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, $installments);

        $payment = $this->applyPayment($loan, $customer, $channel, 150);
        $loan->refresh();

        $this->assertSame(150.0, (float) $loan->amount_paid);
        $this->assertSame(450.0, (float) $loan->outstanding_balance);
        $this->assertSame('active', $loan->status);

        $admin = $this->makeAdminWithRefundPermission();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 50,
                'reason' => 'Partial refund after three months paid.',
            ])
            ->assertRedirect();

        $loan->refresh();
        $this->assertSame(100.0, (float) $loan->amount_paid);
        $this->assertSame(500.0, (float) $loan->outstanding_balance);
        $this->assertSame('active', $loan->status);
        $this->assertNull($loan->loan_settled_date);
    }

    public function test_refund_from_normal_due_installment_reopens_partially_paid_period(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);

        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [
            ['expected' => 500, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 500, 'due_date' => now()->toDateString()],
        ]);

        $payment = $this->applyPayment($loan, $customer, $channel, 500);
        $admin = $this->makeAdminWithRefundPermission();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 200,
                'reason' => 'Refund on first due installment.',
            ])
            ->assertRedirect();

        $loan->refresh();
        $firstSchedule = $loan->paymentSchedules()->orderBy('period_number')->first();

        $this->assertSame(300.0, (float) $loan->amount_paid);
        $this->assertSame(700.0, (float) $loan->outstanding_balance);
        $this->assertSame('active', $loan->status);
        $this->assertSame(300.0, (float) $firstSchedule->amount_paid);
        $this->assertSame(200.0, (float) $firstSchedule->remaining_amount);
    }

    public function test_refund_from_advance_repayment_reverses_latest_schedule_allocations(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);

        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [
            ['expected' => 500, 'due_date' => now()->subMonths(2)->toDateString()],
            ['expected' => 500, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 500, 'due_date' => now()->toDateString()],
        ]);

        $this->applyPayment($loan, $customer, $channel, 500);
        $advancePayment = $this->applyPayment($loan, $customer, $channel, 800);

        $loan->refresh();
        $this->assertSame(1300.0, (float) $loan->amount_paid);
        $this->assertSame(200.0, (float) $loan->outstanding_balance);

        $admin = $this->makeAdminWithRefundPermission();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $advancePayment->id,
                'amount' => 300,
                'reason' => 'Refund part of advance repayment.',
            ])
            ->assertRedirect();

        $loan->refresh();
        $lastSchedule = $loan->paymentSchedules()->orderByDesc('period_number')->first();

        $this->assertSame(1000.0, (float) $loan->amount_paid);
        $this->assertSame(500.0, (float) $loan->outstanding_balance);
        $this->assertSame('active', $loan->status);
        $this->assertSame(200.0, (float) $lastSchedule->amount_paid);
        $this->assertSame(300.0, (float) $lastSchedule->remaining_amount);
    }

    public function test_duplicated_final_installment_refund_restores_outstanding_balance(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);

        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [
            ['expected' => 1000, 'due_date' => now()->subMonths(2)->toDateString()],
            ['expected' => 1000, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 1000, 'due_date' => now()->toDateString()],
        ]);

        $this->applyPayment($loan, $customer, $channel, 1000);
        $this->applyPayment($loan, $customer, $channel, 1000);
        $duplicateFinal = $this->applyPayment($loan, $customer, $channel, 1000);

        $loan->refresh();
        $this->assertSame('settled', $loan->status);

        $admin = $this->makeAdminWithRefundPermission();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $duplicateFinal->id,
                'amount' => 1000,
                'reason' => 'Final installment deducted twice.',
            ])
            ->assertRedirect();

        $loan->refresh();
        $this->assertSame(2000.0, (float) $loan->amount_paid);
        $this->assertSame(1000.0, (float) $loan->outstanding_balance);
        $this->assertSame('active', $loan->status);
    }

    public function test_settled_loan_refund_reactivates_when_net_paid_falls_below_expected_settlement(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 800]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 800);
        $loan->refresh();
        $this->assertSame('settled', $loan->status);

        $admin = $this->makeAdminWithRefundPermission();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 250,
                'reason' => 'Duplicate deduction reversed.',
            ]);

        $loan->refresh();
        $this->assertSame('active', $loan->status);
        $this->assertNull($loan->loan_settled_date);
        $this->assertSame(550.0, (float) $loan->amount_paid);
        $this->assertSame(250.0, (float) $loan->outstanding_balance);
    }

    public function test_payment_above_total_loan_amount_creates_suspense_only_for_excess(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 2000]]);

        $this->applyPayment($loan, $customer, $channel, 2300);
        $loan->refresh();

        $this->assertSame(2300.0, (float) $loan->amount_paid);
        $this->assertSame(0.0, (float) $loan->outstanding_balance);
        $this->assertSame(300.0, $this->ledger->calculateSuspenseAmount($loan));
        $this->assertSame('settled', $loan->status);
    }

    public function test_refund_reduces_suspense_before_reversing_schedule_allocations(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 2000]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 2300);
        $schedule = $loan->paymentSchedules()->first();
        $this->assertSame(2000.0, (float) $schedule->amount_paid);

        $admin = $this->makeAdminWithRefundPermission();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 150,
                'reason' => 'Refund suspense portion only.',
            ])
            ->assertRedirect();

        $loan->refresh();
        $schedule->refresh();

        $this->assertSame(2150.0, (float) $loan->amount_paid);
        $this->assertSame(0.0, (float) $loan->outstanding_balance);
        $this->assertSame(150.0, $this->ledger->calculateSuspenseAmount($loan));
        $this->assertSame(2000.0, (float) $schedule->amount_paid);
        $this->assertSame(0.0, (float) $schedule->remaining_amount);
        $this->assertSame('settled', $loan->status);
    }

    public function test_full_suspense_refund_does_not_unwind_schedule(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 2000]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 2300);

        $admin = $this->makeAdminWithRefundPermission();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 300,
                'reason' => 'Refund full suspense overpayment.',
            ])
            ->assertRedirect();

        $loan->refresh();
        $schedule = $loan->paymentSchedules()->first();

        $this->assertSame(2000.0, (float) $loan->amount_paid);
        $this->assertSame(0.0, (float) $loan->outstanding_balance);
        $this->assertSame(0.0, $this->ledger->calculateSuspenseAmount($loan));
        $this->assertSame(2000.0, (float) $schedule->amount_paid);
        $this->assertSame('settled', $loan->status);
    }

    public function test_multiple_partial_refunds_against_one_repayment(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 1000]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 500);
        $admin = $this->makeAdminWithRefundPermission();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 200,
                'reason' => 'Partial refund 1',
            ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 150,
                'reason' => 'Partial refund 2',
            ]);

        $payment->refresh();
        $this->assertSame(150.0, $payment->refundableAmountRemaining());

        $loan->refresh();
        $this->assertSame(150.0, (float) $loan->amount_paid);
        $this->assertSame(850.0, (float) $loan->outstanding_balance);
    }

    public function test_refund_cannot_exceed_remaining_refundable_amount(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 500]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 500);
        $admin = $this->makeAdminWithRefundPermission();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 600,
                'reason' => 'Too much',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_refund_row_cannot_be_refunded(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 400]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 400);
        $admin = $this->makeAdminWithRefundPermission();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 100,
                'reason' => 'First refund',
            ]);

        $refundRow = LoanRepayment::query()
            ->where('transaction_type', LoanRepayment::TRANSACTION_TYPE_REFUND)
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $refundRow->id,
                'amount' => 50,
                'reason' => 'Invalid refund of refund',
            ])
            ->assertSessionHasErrors('loan_repayment_id');
    }

    public function test_customer_statement_running_balance_uses_net_paid(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 600]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 300);
        app(LoanRepaymentRefundService::class)->applyRefund(
            $loan->fresh(),
            $payment,
            100,
            'Statement balance test'
        );

        $statement = $this->buildCustomerStatement($customer, $loan->id);
        $paymentTxn = $statement->firstWhere('type', 'payment');
        $refundTxn = $statement->firstWhere('type', 'refund');

        $this->assertSame(300.0, (float) $paymentTxn['net_paid']);
        $this->assertSame(300.0, (float) $paymentTxn['outstanding_balance']);
        $this->assertSame(200.0, (float) $refundTxn['net_paid']);
        $this->assertSame(400.0, (float) $refundTxn['outstanding_balance']);
    }

    public function test_customer_statement_includes_payments_and_refunds_across_loans(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);

        $loanA = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 300]]);
        $loanB = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 400]]);

        $paymentA = $this->applyPayment($loanA, $customer, $channel, 300);
        $this->applyPayment($loanB, $customer, $channel, 400);

        app(LoanRepaymentRefundService::class)->applyRefund(
            $loanA->fresh(),
            $paymentA,
            100,
            'Overpayment on loan A'
        );

        $statement = $this->buildCustomerStatement($customer);

        $this->assertTrue($statement->contains(fn ($txn) => $txn['type'] === 'payment' && $txn['loan']->id === $loanA->id));
        $this->assertTrue($statement->contains(fn ($txn) => $txn['type'] === 'payment' && $txn['loan']->id === $loanB->id));
        $this->assertTrue($statement->contains(fn ($txn) => $txn['type'] === 'refund' && $txn['loan']->id === $loanA->id));
    }

    public function test_report_collection_totals_net_refunds(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 500]]);

        $payment = $this->applyPayment($loan, $customer, $channel, 500);

        app(LoanRepaymentRefundService::class)->applyRefund(
            $loan->fresh(),
            $payment,
            100,
            'Report netting test'
        );

        $netCollections = (float) LoanRepayment::query()
            ->join('repayments', 'repayments.id', '=', 'loan_repayments.repayment_id')
            ->where('repayments.status', 'completed')
            ->sum('loan_repayments.amount');

        $this->assertSame(400.0, $netCollections);
    }

    public function test_unauthorized_admin_cannot_record_refund(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = $this->makeCompany($suffix);
        $loanProduct = $this->makeLoanProduct($company, $suffix);
        $customer = $this->makeCustomer($company, $loanProduct, $suffix);
        $channel = $this->makeChannel($suffix);
        $loan = $this->makeLoanWithSchedules($customer, $loanProduct, $channel, [['expected' => 200]]);
        $payment = $this->applyPayment($loan, $customer, $channel, 200);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'No',
            'last_name' => 'Refund',
            'email' => 'no-refund-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 50,
                'reason' => 'Should fail',
            ])
            ->assertForbidden();
    }

    private function buildCustomerStatement(Customer $customer, ?int $loanId = null): \Illuminate\Support\Collection
    {
        $controller = app(StatementController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildTransactionHistory');
        $method->setAccessible(true);

        $loansQuery = $customer->loans()->with(['loanProduct', 'customerGroup', 'accruals']);
        if ($loanId) {
            $loansQuery->where('id', $loanId);
        }

        return $method->invoke($controller, $loansQuery->get());
    }
}
