<?php

namespace Tests\Feature\Sms;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\SmsMessage;
use App\Services\CustomerNotificationService;
use App\Sms\Enums\SmsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\SeedsSmsTemplates;
use Tests\TestCase;

class RepaymentSmsNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSmsTemplates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSmsTemplates();
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
    }

    public function test_repayment_completed_uses_partial_template_when_balance_remains(): void
    {
        [$customer, $loan] = $this->makeCustomerWithLoan(500);
        $repayment = $this->makeRepayment($customer, 200);

        app(CustomerNotificationService::class)->sendRepaymentCompleted($repayment);

        $sms = SmsMessage::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($sms);
        $this->assertSame('repayment_completed', $sms->message_type);
        $this->assertSame(SmsCategory::Payment, $sms->message_category);
        $this->assertStringContainsString('Outstanding balance', $sms->message_body);
    }

    public function test_repayment_completed_uses_full_template_when_balance_settled(): void
    {
        [$customer, $loan] = $this->makeCustomerWithLoan(0);
        $loan->update(['outstanding_balance' => 0, 'status' => 'active']);
        $repayment = $this->makeRepayment($customer, 200);

        app(CustomerNotificationService::class)->sendRepaymentCompleted($repayment);

        $sms = SmsMessage::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($sms);
        $this->assertStringContainsString('settled', $sms->message_body);
    }

    public function test_repayment_failed_sends_sms_once(): void
    {
        [$customer] = $this->makeCustomerWithLoan(800);
        $repayment = $this->makeRepayment($customer, 150);

        $service = app(CustomerNotificationService::class);
        $service->sendRepaymentFailed($repayment, 'gateway');
        $service->sendRepaymentFailed($repayment->fresh(), 'gateway');

        $this->assertSame(1, SmsMessage::query()->where('customer_id', $customer->id)->count());
        $this->assertNotNull(data_get($repayment->fresh()->metadata, 'sms_repayment_failed_sent_at'));
    }

    /**
     * @return array{0: Customer, 1: Loan}
     */
    private function makeCustomerWithLoan(float $outstanding): array
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Repay SMS Co '.$suffix,
            'slug' => 'repay-sms-'.$suffix,
            'code' => 'RS'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Repay Product',
            'code' => 'RP'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Repay',
            'last_name' => 'Sms',
            'email' => 'repay-sms-'.$suffix.'@example.com',
            'phone' => '0977000005',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $channel = Channel::create([
            'name' => 'Test Channel '.$suffix,
            'code' => 'CH'.$suffix,
            'type' => 'mobile_wallet',
            'is_active' => true,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.$suffix,
            'principal_amount' => max($outstanding, 200),
            'processing_fee' => 0,
            'total_amount' => max($outstanding, 200),
            'amount_paid' => 0,
            'outstanding_balance' => $outstanding,
            'tenure_months' => 1,
            'loan_start_date' => now()->subMonth()->toDateString(),
            'loan_end_date' => now()->addMonth()->toDateString(),
            'first_payment_date' => now()->addDays(10)->toDateString(),
            'last_payment_date' => now()->addDays(40)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now()->subMonth(),
        ]);

        return [$customer, $loan];
    }

    private function makeRepayment(Customer $customer, float $amount): Repayment
    {
        $channel = Channel::query()->first();

        return Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel?->id,
            'repayment_number' => 'RP-'.Str::upper(Str::random(6)),
            'total_amount' => $amount,
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
