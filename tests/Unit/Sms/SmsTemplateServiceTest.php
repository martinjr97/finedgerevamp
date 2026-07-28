<?php

namespace Tests\Unit\Sms;

use App\Models\SmsTemplate;
use App\Sms\Enums\SmsCategory;
use App\Sms\Services\SmsTemplateService;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsSmsTemplates;
use Tests\TestCase;

class SmsTemplateServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSmsTemplates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSmsTemplates();
    }

    public function test_render_substitutes_placeholders_and_formats_money(): void
    {
        $body = app(SmsTemplateService::class)->render('repayment_success_partial', [
            'name' => 'Jane',
            'amount' => 500,
            'balance' => 1200.5,
        ]);

        $this->assertNotNull($body);
        $this->assertStringContainsString('Jane', $body);
        $this->assertStringContainsString('K500', $body);
        $this->assertStringContainsString('K1,200.50', $body);
    }

    public function test_render_returns_null_for_inactive_template(): void
    {
        SmsTemplate::query()->where('key', 'customer_approved')->update(['is_active' => false]);

        $body = app(SmsTemplateService::class)->render('customer_approved', [
            'name' => 'Jane',
            'phone' => '260971234567',
            'pin' => '1234',
        ]);

        $this->assertNull($body);
    }

    public function test_render_returns_null_when_body_exceeds_max_length(): void
    {
        SmsTemplate::query()->where('key', 'customer_approved')->update([
            'body' => str_repeat('X', 200),
            'max_length' => 50,
        ]);

        $body = app(SmsTemplateService::class)->render('customer_approved', [
            'name' => 'Jane',
            'phone' => '260971234567',
            'pin' => '1234',
        ]);

        $this->assertNull($body);
    }

    public function test_queue_for_customer_skips_when_template_too_long(): void
    {
        config(['sms.enabled' => true, 'sms.provider' => 'log']);

        SmsTemplate::query()->where('key', 'customer_approved')->update([
            'body' => 'PIN {PIN} '.str_repeat('X', 200),
            'max_length' => 50,
        ]);

        $customer = $this->makeCustomer();

        $record = app(SmsTemplateService::class)->queueForCustomer(
            $customer,
            'customer_approved',
            ['name' => 'Jane', 'phone' => $customer->phone, 'pin' => '1234'],
            'customer_approved',
        );

        $this->assertNotNull($record);
        $this->assertSame('skipped', $record->status);
        $this->assertSame('too_long', $record->skip_reason);
    }

    public function test_reminder_template_key_maps_reminder_types(): void
    {
        $service = app(SmsTemplateService::class);

        $this->assertSame('reminder_1_week_before', $service->reminderTemplateKey('1_week_before'));
        $this->assertSame('reminder_missed_2', $service->reminderTemplateKey('missed_2'));
    }

    public function test_category_for_key_returns_template_category(): void
    {
        $category = app(SmsTemplateService::class)->categoryForKey('pin_reset_admin');

        $this->assertSame(SmsCategory::Security, $category);
    }

    private function makeCustomer(): \App\Models\Customer
    {
        $suffix = strtolower(\Illuminate\Support\Str::random(6));
        $company = \App\Models\Company::create([
            'name' => 'SMS Co '.$suffix,
            'slug' => 'sms-'.$suffix,
            'code' => 'S'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = \App\Models\LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'SMS Product',
            'code' => 'SP'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        return \App\Models\Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Sms',
            'last_name' => 'Customer',
            'email' => 'sms-'.$suffix.'@example.com',
            'phone' => '0977000002',
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }
}
