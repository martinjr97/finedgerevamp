<?php

namespace Tests\Feature\Sms;

use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Jobs\SendSmsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerPasswordResetOtpSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_otp_queues_sms_without_plaintext_logging(): void
    {
        config(['sms.enabled' => false, 'sms.provider' => 'log']);
        Queue::fake();
        Log::spy();

        $customer = $this->createCustomer();

        $response = $this->post(route('customer.password.email'), [
            'phone' => $customer->phone,
            'national_id' => $customer->national_id,
        ]);

        $response->assertRedirect(route('customer.password.verify-otp'));

        $this->assertDatabaseHas('sms_messages', [
            'customer_id' => $customer->id,
            'message_type' => 'password_reset_otp',
            'message_category' => SmsCategory::Otp->value,
            'status' => 'skipped',
            'message_body' => '[REDACTED OTP MESSAGE]',
        ]);

        Queue::assertNothingPushed();

        Log::shouldNotHaveReceived('info', function ($message, $context) {
            return is_array($context) && array_key_exists('otp', $context);
        });
    }

    public function test_password_reset_otp_dispatches_job_when_enabled(): void
    {
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
        Queue::fake();

        $customer = $this->createCustomer();

        $this->post(route('customer.password.email'), [
            'phone' => $customer->phone,
            'national_id' => $customer->national_id,
        ]);

        Queue::assertPushed(SendSmsJob::class);

        $record = SmsMessage::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('queued', $record->status);
        $this->assertSame('[REDACTED OTP MESSAGE]', $record->message_body);
    }

    private function createCustomer(): Customer
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Test Co '.$suffix,
            'slug' => 'test-'.$suffix,
            'code' => 'T'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Test Product '.$suffix,
            'code' => 'TP'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        return Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer-'.$suffix.'@example.com',
            'phone' => '0977000001',
            'national_id' => '123456/'.$suffix.'/1',
            'password' => 'password',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }
}
