<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Services\RepaymentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_statement_uses_lifetime_ledger_layout(): void
    {
        $context = $this->makeContext();
        $loan = $this->makeLoan($context);
        $this->pay($loan, $context['customer'], $context['channel'], 250);

        $response = $this->actingAs($context['customer'], 'customer')
            ->get(route('customer.statement'));

        $response->assertOk();
        $response->assertSee('Account statement', false);
        $response->assertSee('Transaction ledger', false);
        $response->assertDontSee('Expected settlement', false);
        $response->assertDontSee('Net paid', false);
        $response->assertDontSee('Total refunded', false);
        $response->assertSee('Running balance', false);
        $response->assertSee($loan->loan_number);
        $response->assertSee('Download PDF', false);
        $response->assertDontSee('Total Borrowed', false);
    }

    public function test_customer_can_filter_statement_by_loan_and_download_pdf(): void
    {
        $context = $this->makeContext();
        $loanA = $this->makeLoan($context);
        $loanB = $this->makeLoan($context);
        $this->pay($loanA, $context['customer'], $context['channel'], 100);

        $html = $this->actingAs($context['customer'], 'customer')
            ->get(route('customer.statement', ['loan_id' => $loanB->id]));

        $html->assertOk();
        $html->assertSee($loanB->loan_number);
        $html->assertSee('All loans', false);

        $pdf = $this->actingAs($context['customer'], 'customer')
            ->get(route('customer.statement.pdf', ['loan_id' => $loanB->id]));

        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString($loanB->loan_number, (string) $pdf->headers->get('content-disposition'));
    }

    /**
     * @return array{customer: Customer, channel: Channel, loanProduct: LoanProduct, company: Company}
     */
    private function makeContext(): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Portal Stmt Co '.$suffix,
            'slug' => 'portal-stmt-co-'.$suffix,
            'code' => 'PSC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_primary' => true,
        ]);

        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Portal Stmt Product '.$suffix,
            'code' => 'PSP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $channel = Channel::create([
            'name' => 'Portal Stmt Channel '.$suffix,
            'code' => 'PSC-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $customer = $this->withCustomerSecurityQuestion(Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Portal',
            'last_name' => 'Statement',
            'email' => 'portal-stmt-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]));

        return compact('customer', 'channel', 'loanProduct', 'company');
    }

    private function makeLoan(array $context): Loan
    {
        return Loan::create([
            'customer_id' => $context['customer']->id,
            'loan_product_id' => $context['loanProduct']->id,
            'channel_id' => $context['channel']->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(8)),
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'interest_accrued' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 3,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonths(2)->toDateString(),
            'first_payment_date' => now()->toDateString(),
            'status' => 'active',
            'disbursement_status' => 'completed',
            'approved_at' => now()->subMonth(),
            'disbursed_at' => now()->subMonth(),
        ]);
    }

    private function pay(Loan $loan, Customer $customer, Channel $channel, float $amount): void
    {
        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => $amount,
            'phone_number' => $customer->phone,
            'status' => 'completed',
            'processed_at' => now(),
            'metadata' => [
                'repayment_type' => 'partial',
                'loan_id' => $loan->id,
            ],
        ]);

        app(RepaymentProcessingService::class)->applyRepaymentToLoans(
            $repayment,
            $customer,
            'partial',
            $loan->id,
            $amount,
            'Test payment'
        );
    }
}
