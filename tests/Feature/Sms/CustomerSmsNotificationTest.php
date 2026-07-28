<?php

namespace Tests\Feature\Sms;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\LoanProduct;
use App\Models\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Jobs\SendSmsJob;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\SeedsSmsTemplates;
use Tests\TestCase;

class CustomerSmsNotificationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSmsTemplates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSmsTemplates();
        config(['sms.enabled' => true, 'sms.provider' => 'log']);
    }

    public function test_customer_approval_queues_security_sms_with_redacted_body(): void
    {
        Notification::fake();
        Queue::fake();

        $admin = $this->makeAdmin(['approvals.approve']);
        $customer = $this->makePendingCustomer($admin);

        $this->actingAs($admin, 'admin')->post(
            route('admin.approvals.customers.approve', $customer),
            ['notes' => 'Approved']
        )->assertRedirect(route('admin.approvals.index'));

        $this->assertDatabaseHas('sms_messages', [
            'customer_id' => $customer->id,
            'message_type' => 'customer_approved',
            'message_category' => SmsCategory::Security->value,
            'message_body' => '[REDACTED OTP MESSAGE]',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_admin_pin_reset_queues_security_sms(): void
    {
        Notification::fake();
        Queue::fake();

        $admin = $this->makeAdmin(['customers.reset-pin', 'customers.view']);
        $customer = $this->makeActiveCustomer($admin);

        $this->actingAs($admin, 'admin')->post(
            route('admin.customers.reset-pin', $customer)
        )->assertRedirect(route('admin.customers.show', $customer));

        $this->assertDatabaseHas('sms_messages', [
            'customer_id' => $customer->id,
            'message_type' => 'pin_reset_admin',
            'message_category' => SmsCategory::Security->value,
            'message_body' => '[REDACTED OTP MESSAGE]',
        ]);

        Queue::assertPushed(SendSmsJob::class);
    }

    private function makeAdmin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'SMS Admin Co '.$suffix,
            'slug' => 'sms-admin-'.$suffix,
            'code' => 'SA'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'sms-admin-'.$suffix.'@example.com',
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

    private function makePendingCustomer(Admin $admin): Customer
    {
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'SMS Product',
            'code' => 'SMS-APPROVE',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Pending',
            'last_name' => 'Sms',
            'email' => 'pending-sms@example.com',
            'phone' => '0977000003',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'kyc_status' => 'in_review',
        ]);

        KycDocument::create([
            'customer_id' => $customer->id,
            'document_type' => 'nrc',
            'front_image_path' => 'kyc/front.jpg',
            'status' => 'pending',
        ]);

        return $customer;
    }

    private function makeActiveCustomer(Admin $admin): Customer
    {
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'SMS Product Active',
            'code' => 'SMS-ACTIVE',
            'category' => 'character',
            'is_active' => true,
        ]);

        return Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Active',
            'last_name' => 'Sms',
            'email' => 'active-sms@example.com',
            'phone' => '0977000004',
            'password' => '1111',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }
}
