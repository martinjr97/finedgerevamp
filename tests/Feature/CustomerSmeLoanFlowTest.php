<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSmeLoanFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{
     *     customer: Customer,
     *     loanProduct: LoanProduct,
     *     loanRate: LoanRate,
     *     channel: Channel
     * }
     */
    private function createSmeCustomerContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'SME Customer Co '.$suffix,
            'slug' => 'sme-customer-co-'.$suffix,
            'code' => 'SMEC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
            'monthly_cut_off_day' => 5,
            'pay_day' => 25,
            'maximum_loan_tenure_months' => 12,
            'instalment_cross_over_percentage' => 40,
        ]);

        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'SME Product '.$suffix,
            'code' => 'SME-CUST-'.$suffix,
            'category' => 'sme',
            'is_active' => true,
            'accrual_type' => 'daily',
        ]);

        $loanRateType = LoanRateType::create([
            'loan_product_id' => $loanProduct->id,
            'name' => 'SME Loan Rate Type '.$suffix,
            'code' => 'SME-LRT-'.$suffix,
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        $company->update(['loan_rate_type_id' => $loanRateType->id]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $loanRateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'daily_rate' => 0.03,
            'weekly_rate' => null,
            'arrear_rate' => 0.03,
            'is_active' => true,
        ]);

        $channel = Channel::create([
            'name' => 'Mobile Wallet '.$suffix,
            'code' => 'MW-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'customer_type' => 'company',
            'registered_name' => 'Sunrise Ventures '.$suffix,
            'first_name' => 'Sunrise',
            'last_name' => 'Ventures',
            'email' => 'sme-flow-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
            'net_salary' => 20000,
            'maximum_loan_take' => 50000,
            'must_change_pin' => false,
        ]);

        return [
            'customer' => $customer,
            'loanProduct' => $loanProduct,
            'loanRate' => $loanRate,
            'channel' => $channel,
        ];
    }

    public function test_sme_customer_calculation_uses_start_date_plus_selected_tenure(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-06 09:30:00'));

        $context = $this->createSmeCustomerContext();
        $customer = $context['customer'];
        $channel = $context['channel'];

        $response = $this->actingAs($customer, 'customer')
            ->withSession([
                'loan_application.amount' => 9000,
                'loan_application.channel_id' => $channel->id,
                'loan_application.destination_validated' => true,
                'loan_application.disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
                'loan_application.disbursement_phone_number' => $customer->phone,
                'loan_application.phone_number' => $customer->phone,
            ])
            ->post(route('customer.loans.calculate.store'), [
                'tenure_months' => 3,
            ]);

        $response->assertOk();
        $response->assertViewHas('showCalculation', true);
        $response->assertViewHas('loanStartDate', function ($loanStartDate): bool {
            return $loanStartDate instanceof Carbon
                && $loanStartDate->toDateString() === '2026-03-06';
        });
        $response->assertViewHas('loanEndDate', function ($loanEndDate): bool {
            return $loanEndDate instanceof Carbon
                && $loanEndDate->toDateString() === '2026-06-06';
        });
    }

    public function test_sme_customer_submission_sets_monthly_repayment_dates_from_start_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-06 08:00:00'));

        $context = $this->createSmeCustomerContext();
        $customer = $context['customer'];
        $channel = $context['channel'];
        $loanRate = $context['loanRate'];

        $response = $this->actingAs($customer, 'customer')
            ->withSession([
                'loan_application.amount' => 9000,
                'loan_application.channel_id' => $channel->id,
                'loan_application.destination_validated' => true,
                'loan_application.disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
                'loan_application.disbursement_phone_number' => $customer->phone,
                'loan_application.phone_number' => $customer->phone,
                'loan_application.tenure_months' => 3,
                'loan_application.loan_rate_id' => $loanRate->id,
            ])
            ->post(route('customer.loans.store'));

        $response->assertRedirect(route('customer.dashboard'));

        $loan = Loan::query()->where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($loan);
        $this->assertSame('2026-04-06', $loan->first_payment_date?->toDateString());
        $this->assertSame('2026-06-06', $loan->loan_end_date?->toDateString());
        $this->assertSame('2026-06-06', $loan->last_payment_date?->toDateString());
    }
}
