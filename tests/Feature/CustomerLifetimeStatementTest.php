<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Services\CustomerLifetimeStatementService;
use App\Services\LoanRepaymentLedgerService;
use App\Services\LoanRepaymentRefundService;
use App\Services\RepaymentProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerLifetimeStatementTest extends TestCase
{
    use RefreshDatabase;

    private LoanRepaymentLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = app(LoanRepaymentLedgerService::class);
    }

    private function makeAdmin(): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Stmt Co '.$suffix,
            'slug' => 'stmt-co-'.$suffix,
            'code' => 'ST'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Stmt',
            'last_name' => 'Admin',
            'email' => 'stmt-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        Permission::firstOrCreate(['name' => 'customers.view', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'repayments.refund', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['customers.view', 'repayments.refund']);

        return $admin;
    }

    private function makeCustomer(Company $company, LoanProduct $product, string $suffix): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Lifetime',
            'last_name' => 'Customer',
            'email' => 'lifetime-'.$suffix.'@example.com',
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
            'name' => 'Stmt Channel '.$suffix,
            'code' => 'STC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array{expected: float, due_date?: string}>  $installments
     */
    private function makeLoan(
        Customer $customer,
        LoanProduct $product,
        Channel $channel,
        array $installments,
        ?Carbon $disbursedAt = null
    ): Loan {
        $totalExpected = array_sum(array_column($installments, 'expected'));
        $disbursedAt ??= now()->subMonths(2);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => $totalExpected,
            'processing_fee' => 0,
            'total_amount' => $totalExpected,
            'amount_paid' => 0,
            'outstanding_balance' => $totalExpected,
            'tenure_months' => count($installments),
            'loan_start_date' => $disbursedAt->toDateString(),
            'loan_end_date' => $disbursedAt->copy()->addMonths(count($installments))->toDateString(),
            'first_payment_date' => $disbursedAt->copy()->addMonth()->toDateString(),
            'last_payment_date' => $disbursedAt->copy()->addMonths(count($installments))->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => $disbursedAt,
        ]);

        foreach ($installments as $index => $installment) {
            LoanPaymentSchedule::create([
                'loan_id' => $loan->id,
                'period_number' => $index + 1,
                'due_date' => $installment['due_date'] ?? $disbursedAt->copy()->addMonths($index + 1)->toDateString(),
                'expected_amount' => $installment['expected'],
                'amount_paid' => 0,
                'remaining_amount' => $installment['expected'],
                'status' => 'upcoming',
                'days_overdue' => 0,
            ]);
        }

        return $loan;
    }

    private function pay(Loan $loan, Customer $customer, Channel $channel, float $amount): void
    {
        $repayment = \App\Models\Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => \App\Models\Repayment::generateRepaymentNumber(),
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
            'Statement test payment'
        );
    }

    public function test_statement_page_requires_permission_and_shows_button_on_profile(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->makeAdmin();
        $company = Company::find($admin->company_id);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Stmt Product',
            'code' => 'STP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('View Statement');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.statement', $customer))
            ->assertOk()
            ->assertSee('Lifetime Statement')
            ->assertSee('Transaction ledger');
    }

    public function test_statement_includes_disbursement_schedule_payment_and_refund_rows(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->makeAdmin();
        $company = Company::find($admin->company_id);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Stmt Product',
            'code' => 'STP2-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'a');
        $channel = $this->makeChannel($suffix);

        $loan = $this->makeLoan($customer, $product, $channel, [
            ['expected' => 500, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 500, 'due_date' => now()->addMonth()->toDateString()],
        ]);

        $this->pay($loan, $customer, $channel, 300);
        $payment = LoanRepayment::query()->where('loan_id', $loan->id)->where('amount', '>', 0)->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.refund', $loan), [
                'loan_repayment_id' => $payment->id,
                'amount' => 100,
                'reason' => 'Statement test refund',
            ]);

        $statement = app(CustomerLifetimeStatementService::class)->build($customer);
        $types = $statement['rows']->pluck('transaction_type')->unique()->values()->all();

        $this->assertContains('disbursement', $types);
        $this->assertContains('schedule', $types);
        $this->assertContains('payment', $types);
        $this->assertContains('refund', $types);

        $disbursement = $statement['rows']->firstWhere('transaction_type', 'disbursement');
        $this->assertSame(1000.0, (float) $disbursement['debit']);
        $this->assertTrue($disbursement['is_cash']);
    }

    public function test_running_balance_and_summary_reconcile_with_ledger(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->makeAdmin();
        $company = Company::find($admin->company_id);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Stmt Product',
            'code' => 'STP3-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'b');
        $channel = $this->makeChannel($suffix.'b');

        $loanA = $this->makeLoan($customer, $product, $channel, [['expected' => 1000]], now()->subMonths(3));
        $loanB = $this->makeLoan($customer, $product, $channel, [['expected' => 500]], now()->subMonths(2));

        $this->pay($loanA, $customer, $channel, 1200);
        $this->pay($loanB, $customer, $channel, 100);

        $loanA->refresh();
        $loanB->refresh();

        $statement = app(CustomerLifetimeStatementService::class)->build($customer);
        $summary = $statement['summary'];
        $closing = $statement['closing_balance'];

        $expectedTotal = $this->ledger->getExpectedSettlementAmount($loanA)
            + $this->ledger->getExpectedSettlementAmount($loanB);
        $netPaidTotal = $this->ledger->calculateNetPaid($loanA) + $this->ledger->calculateNetPaid($loanB);
        $outstandingTotal = $this->ledger->calculateOutstandingBalance($loanA)
            + $this->ledger->calculateOutstandingBalance($loanB);
        $suspenseTotal = $this->ledger->calculateSuspenseAmount($loanA)
            + $this->ledger->calculateSuspenseAmount($loanB);

        $this->assertSame(2, $summary['loans_collected']);
        $this->assertEqualsWithDelta($expectedTotal, $summary['total_expected_settlement'], 0.01);
        $this->assertEqualsWithDelta($netPaidTotal, $summary['total_net_paid'], 0.01);
        $this->assertEqualsWithDelta($outstandingTotal, $summary['total_outstanding'], 0.01);
        $this->assertEqualsWithDelta($suspenseTotal, $summary['total_suspense'], 0.01);

        $netOwed = round($expectedTotal - $netPaidTotal, 2);
        if ($netOwed < 0) {
            $this->assertEqualsWithDelta(abs($netOwed), $closing['customer_credit'], 0.01);
            $this->assertSame(0.0, $closing['balance_owed']);
        } else {
            $this->assertEqualsWithDelta($netOwed, $closing['balance_owed'], 0.01);
            $this->assertSame(0.0, $closing['customer_credit']);
        }
    }

    public function test_date_filter_applies_opening_balance(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Filter Co',
            'slug' => 'filter-co-'.$suffix,
            'code' => 'FC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Filter Product',
            'code' => 'FP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'c');
        $channel = $this->makeChannel($suffix.'c');

        $disbursed = now()->subMonths(6);
        $loan = $this->makeLoan($customer, $product, $channel, [['expected' => 800]], $disbursed);
        $this->pay($loan, $customer, $channel, 200);

        $fromDate = now()->subMonths(1)->startOfDay();
        $full = app(CustomerLifetimeStatementService::class)->build($customer);
        $filtered = app(CustomerLifetimeStatementService::class)->build($customer, $fromDate, null, null);

        $this->assertGreaterThan(0, $filtered['opening_balance']['balance_owed'] + $filtered['opening_balance']['customer_credit']);

        $cashBeforeFilter = $full['rows']->filter(fn (array $r) => $r['is_cash'] && $r['date']->lt($fromDate));
        $this->assertTrue($cashBeforeFilter->isNotEmpty());

        $lastBefore = $full['rows']->filter(fn (array $r) => $r['is_cash'] && $r['date']->lt($fromDate))->last();
        if ($lastBefore && isset($lastBefore['running_balance'])) {
            $this->assertEqualsWithDelta(
                $lastBefore['running_balance']['balance_owed'],
                $filtered['opening_balance']['balance_owed'],
                0.02
            );
            $this->assertEqualsWithDelta(
                $lastBefore['running_balance']['customer_credit'],
                $filtered['opening_balance']['customer_credit'],
                0.02
            );
        }
    }

    public function test_suspense_row_appears_when_customer_overpays(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Susp Co',
            'slug' => 'susp-co-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Susp Product',
            'code' => 'SP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'d');
        $channel = $this->makeChannel($suffix.'d');

        $loan = $this->makeLoan($customer, $product, $channel, [['expected' => 1000]]);
        $this->pay($loan, $customer, $channel, 1150);

        $statement = app(CustomerLifetimeStatementService::class)->build($customer);

        $this->assertGreaterThan(0, $statement['summary']['total_suspense']);
        $this->assertTrue($statement['rows']->contains('transaction_type', 'suspense'));
        $this->assertGreaterThan(0, $statement['closing_balance']['customer_credit']);
    }

    public function test_statement_route_denied_without_customers_view_permission(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Denied Co',
            'slug' => 'denied-co-'.$suffix,
            'code' => 'DC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Denied Product',
            'code' => 'DP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'deny');

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'No',
            'last_name' => 'Access',
            'email' => 'no-access-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.statement', $customer))
            ->assertForbidden();
    }

    public function test_print_view_renders_with_filters(): void
    {
        $suffix = Str::lower(Str::random(6));
        $admin = $this->makeAdmin();
        $company = Company::find($admin->company_id);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Print Product',
            'code' => 'PP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'print');
        $channel = $this->makeChannel($suffix.'print');
        $loan = $this->makeLoan($customer, $product, $channel, [['expected' => 300]]);

        $from = now()->subMonths(2)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.statement', [
                'customer' => $customer,
                'print' => 1,
                'from_date' => $from,
                'to_date' => $to,
                'loan_id' => $loan->id,
            ]))
            ->assertOk()
            ->assertSee('Customer Statement')
            ->assertSee($loan->loan_number)
            ->assertSee('Period: '.$from.' to '.$to);
    }

    public function test_schedule_rows_do_not_change_running_balance(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Sched Co',
            'slug' => 'sched-co-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Sched Product',
            'code' => 'SCP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'sched');
        $channel = $this->makeChannel($suffix.'sched');
        $this->makeLoan($customer, $product, $channel, [
            ['expected' => 400, 'due_date' => now()->addMonth()->toDateString()],
        ]);

        $statement = app(CustomerLifetimeStatementService::class)->build($customer);
        $scheduleRow = $statement['rows']->firstWhere('transaction_type', 'schedule');
        $disbursementRow = $statement['rows']->firstWhere('transaction_type', 'disbursement');

        $this->assertNotNull($scheduleRow);
        $this->assertFalse($scheduleRow['is_cash']);
        $this->assertNull($scheduleRow['debit']);
        $this->assertNull($scheduleRow['credit']);
        $this->assertSame(
            $disbursementRow['running_balance']['balance_owed'],
            $scheduleRow['running_balance']['balance_owed']
        );
    }

    public function test_multi_loan_rows_are_sorted_chronologically(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Multi Co',
            'slug' => 'multi-co-'.$suffix,
            'code' => 'MC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Multi Product',
            'code' => 'MP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'multi');
        $channel = $this->makeChannel($suffix.'multi');

        $this->makeLoan($customer, $product, $channel, [['expected' => 500]], now()->subMonths(4));
        $this->makeLoan($customer, $product, $channel, [['expected' => 700]], now()->subMonths(2));

        $dates = app(CustomerLifetimeStatementService::class)
            ->build($customer)['rows']
            ->pluck('date')
            ->map(fn ($d) => $d->timestamp)
            ->values()
            ->all();

        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates);
    }

    public function test_closing_balance_equals_opening_plus_period_cash_movement(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Close Co',
            'slug' => 'close-co-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Close Product',
            'code' => 'CP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'close');
        $channel = $this->makeChannel($suffix.'close');
        $loan = $this->makeLoan($customer, $product, $channel, [['expected' => 1000]], now()->subMonths(3));
        $this->pay($loan, $customer, $channel, 250);

        $from = now()->subMonth()->startOfDay();
        $statement = app(CustomerLifetimeStatementService::class)->build($customer, $from, null, null);

        $openingNet = $statement['opening_balance']['balance_owed'] - $statement['opening_balance']['customer_credit'];
        $periodNet = $statement['rows']->sum(function (array $row): float {
            if (! $row['is_cash']) {
                return 0.0;
            }

            return (float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0);
        });
        $closingNet = $statement['closing_balance']['balance_owed'] - $statement['closing_balance']['customer_credit'];

        $this->assertEqualsWithDelta($openingNet + $periodNet, $closingNet, 0.02);
    }

    public function test_loan_filter_limits_rows_to_single_loan(): void
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Loan Filter Co',
            'slug' => 'lf-co-'.$suffix,
            'code' => 'LF'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'LF Product',
            'code' => 'LFP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = $this->makeCustomer($company, $product, $suffix.'e');
        $channel = $this->makeChannel($suffix.'e');

        $loanOne = $this->makeLoan($customer, $product, $channel, [['expected' => 400]]);
        $this->makeLoan($customer, $product, $channel, [['expected' => 600]]);

        $statement = app(CustomerLifetimeStatementService::class)->build($customer, null, null, $loanOne->id);

        $loanIds = $statement['rows']->pluck('loan_id')->unique()->filter()->values();
        $this->assertCount(1, $loanIds);
        $this->assertSame($loanOne->id, $loanIds->first());
    }
}
