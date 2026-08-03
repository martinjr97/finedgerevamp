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
use App\Services\RepaymentProcessingService;
use App\Support\Pdf\FinancialDocumentBranding;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerStatementPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_download_customer_statement_pdf(): void
    {
        $context = $this->makeContext();
        $loan = $this->makeLoan($context, [
            ['expected' => 500, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 500, 'due_date' => now()->addMonth()->toDateString()],
        ]);
        $this->pay($loan, $context['customer'], $context['channel'], 200);

        $outstandingBefore = (float) $loan->fresh()->outstanding_balance;
        $repaymentCountBefore = LoanRepayment::query()->where('loan_id', $loan->id)->count();

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.customers.statement.pdf', $context['customer']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('customer-statement-', $disposition);
        $this->assertStringContainsString('all-loans', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
        $this->assertGreaterThan(1000, strlen((string) $response->getContent()));

        $this->assertSame($outstandingBefore, (float) $loan->fresh()->outstanding_balance);
        $this->assertSame($repaymentCountBefore, LoanRepayment::query()->where('loan_id', $loan->id)->count());
    }

    public function test_statement_pdf_is_posted_only_and_excludes_schedule_rows(): void
    {
        $context = $this->makeContext();
        $loan = $this->makeLoan($context, [
            ['expected' => 500, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 500, 'due_date' => now()->addMonth()->toDateString()],
        ]);
        $this->pay($loan, $context['customer'], $context['channel'], 150);

        $html = $this->renderStatementHtml($context['customer']);

        $this->assertStringContainsString('Customer Statement', $html);
        $this->assertStringContainsString('Scope: All Loans', $html);
        $this->assertStringContainsString('Loan disbursed', $html);
        $this->assertStringContainsString('Repayment received', $html);
        $this->assertStringContainsString($loan->loan_number, $html);
        $this->assertStringNotContainsString('Scheduled installment due', $html);
        $this->assertStringNotContainsString('Upcoming scheduled payments', $html);

        $withSchedules = app(CustomerLifetimeStatementService::class)->build($context['customer']);
        $withoutSchedules = app(CustomerLifetimeStatementService::class)->build(
            $context['customer'],
            includeSchedules: false,
        );

        $this->assertTrue($withSchedules['rows']->contains(fn (array $row): bool => $row['transaction_type'] === 'schedule'));
        $this->assertFalse($withoutSchedules['rows']->contains(fn (array $row): bool => $row['transaction_type'] === 'schedule'));
    }

    public function test_statement_pdf_respects_date_and_loan_filters(): void
    {
        $context = $this->makeContext();
        $loanA = $this->makeLoan($context, [
            ['expected' => 400, 'due_date' => '2026-02-01'],
        ], Carbon::parse('2026-01-10 10:00:00'));
        $loanB = $this->makeLoan($context, [
            ['expected' => 600, 'due_date' => '2026-03-01'],
        ], Carbon::parse('2026-02-15 11:00:00'));

        $from = '2026-02-01';
        $to = '2026-02-28';

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.customers.statement.pdf', [
                'customer' => $context['customer'],
                'loan_id' => $loanB->id,
                'from_date' => $from,
                'to_date' => $to,
            ]));

        $response->assertOk();
        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString($loanB->loan_number, $disposition);
        $this->assertStringContainsString($from, $disposition);
        $this->assertStringContainsString($to, $disposition);

        $html = $this->renderStatementHtml(
            $context['customer'],
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
            $loanB->id,
        );

        $this->assertStringContainsString('Scope: Single Loan', $html);
        $this->assertStringContainsString($loanB->loan_number, $html);
        $this->assertStringNotContainsString($loanA->loan_number, $html);
    }

    public function test_statement_pdf_rejects_foreign_loan_and_invalid_dates(): void
    {
        $context = $this->makeContext();
        $this->makeLoan($context, [['expected' => 300, 'due_date' => now()->addMonth()->toDateString()]]);

        $other = $this->makeContext('other');
        $foreignLoan = $this->makeLoan($other, [['expected' => 300, 'due_date' => now()->addMonth()->toDateString()]]);

        $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.customers.statement.pdf', [
                'customer' => $context['customer'],
                'loan_id' => $foreignLoan->id,
            ]))
            ->assertSessionHasErrors('loan_id');

        $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.customers.statement.pdf', [
                'customer' => $context['customer'],
                'from_date' => '2026-03-01',
                'to_date' => '2026-01-01',
            ]))
            ->assertSessionHasErrors('to_date');
    }

    public function test_statement_pdf_requires_permission_and_html_statement_unchanged(): void
    {
        $context = $this->makeContext();
        $this->makeLoan($context, [
            ['expected' => 500, 'due_date' => now()->subMonth()->toDateString()],
            ['expected' => 500, 'due_date' => now()->addMonth()->toDateString()],
        ]);

        $unauthorized = $this->makeAdmin([]);
        $this->actingAs($unauthorized, 'admin')
            ->get(route('admin.customers.statement.pdf', $context['customer']))
            ->assertForbidden();

        $htmlResponse = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.customers.statement', $context['customer']));

        $htmlResponse->assertOk()
            ->assertSee('Download PDF')
            ->assertSee('Print')
            ->assertSee('Scheduled installment due');
    }

    private function renderStatementHtml(
        Customer $customer,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        ?int $loanId = null,
    ): string {
        $statement = app(CustomerLifetimeStatementService::class)->build(
            $customer,
            $fromDate,
            $toDate,
            $loanId,
            includeSchedules: false,
        );

        return view('pdf.customer-statement', [
            'customer' => $customer,
            'statement' => $statement,
            'company' => $customer->company,
            'branding' => FinancialDocumentBranding::resolve($customer->company),
        ])->render();
    }

    /**
     * @return array{admin: Admin, company: Company, product: LoanProduct, customer: Customer, channel: Channel}
     */
    private function makeContext(string $suffixSeed = ''): array
    {
        $suffix = Str::lower(Str::random(6)).($suffixSeed !== '' ? '-'.$suffixSeed : '');
        $admin = $this->makeAdmin(['customers.view', 'repayments.process']);
        $company = Company::findOrFail($admin->company_id);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Stmt Product '.$suffix,
            'code' => 'STP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = Customer::create([
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
        $channel = Channel::create([
            'name' => 'Stmt Channel '.$suffix,
            'code' => 'STC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        return compact('admin', 'company', 'product', 'customer', 'channel');
    }

    /**
     * @param  array{admin: Admin, company: Company, product: LoanProduct, customer: Customer, channel: Channel}  $context
     * @param  array<int, array{expected: float, due_date?: string}>  $installments
     */
    private function makeLoan(array $context, array $installments, ?Carbon $disbursedAt = null): Loan
    {
        $totalExpected = array_sum(array_column($installments, 'expected'));
        $disbursedAt ??= now()->subMonths(2);

        $loan = Loan::create([
            'customer_id' => $context['customer']->id,
            'loan_product_id' => $context['product']->id,
            'channel_id' => $context['channel']->id,
            'loan_number' => Loan::generateLoanNumber($context['product']),
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
            'Statement PDF test payment'
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Stmt PDF Co '.$suffix,
            'slug' => 'stmt-pdf-co-'.$suffix,
            'code' => 'SP'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Stmt',
            'last_name' => 'Officer',
            'email' => 'stmt-pdf-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        if ($permissions !== []) {
            $admin->givePermissionTo($permissions);
        }

        return $admin;
    }
}
