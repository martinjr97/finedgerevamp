<?php

namespace Tests\Feature\Sms;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\SmsMessage;
use App\Services\CustomerNotificationService;
use App\Sms\Enums\SmsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\SeedsSmsTemplates;
use Tests\TestCase;

class LoanDisbursedSmsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSmsTemplates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSmsTemplates();
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
    }

    public function test_loan_disbursed_queues_loan_category_sms(): void
    {
        $loan = $this->makeDisbursedLoan();

        app(CustomerNotificationService::class)->sendLoanDisbursed($loan);

        $this->assertDatabaseHas('sms_messages', [
            'customer_id' => $loan->customer_id,
            'loan_id' => $loan->id,
            'message_type' => 'loan_disbursed',
            'message_category' => SmsCategory::Loan->value,
        ]);

        $sms = SmsMessage::query()->where('loan_id', $loan->id)->first();
        $this->assertNotNull($sms);
        $this->assertStringContainsString($loan->loan_number, $sms->message_body);
    }

    private function makeDisbursedLoan(): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Disburse SMS Co '.$suffix,
            'slug' => 'disburse-'.$suffix,
            'code' => 'DS'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Disburse Product',
            'code' => 'DP'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Loan',
            'last_name' => 'Sms',
            'email' => 'loan-sms-'.$suffix.'@example.com',
            'phone' => '0977000006',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $channel = Channel::create([
            'name' => 'Disburse Channel '.$suffix,
            'code' => 'DC'.$suffix,
            'type' => 'mobile_wallet',
            'is_active' => true,
        ]);

        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-DISB-'.$suffix,
            'principal_amount' => 1000,
            'processing_fee' => 50,
            'total_amount' => 1050,
            'amount_paid' => 0,
            'outstanding_balance' => 1050,
            'tenure_months' => 3,
            'loan_start_date' => now()->toDateString(),
            'loan_end_date' => now()->addMonths(3)->toDateString(),
            'first_payment_date' => now()->addMonth()->toDateString(),
            'last_payment_date' => now()->addMonths(3)->toDateString(),
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => now(),
            'disbursement_reference' => 'REF-'.$suffix,
        ]);
    }
}
