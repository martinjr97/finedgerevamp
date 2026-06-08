<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminCustomerShowActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Toolbar Test Co '.$suffix,
            'slug' => 'toolbar-test-co-'.$suffix,
            'code' => 'TB'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Action',
            'last_name' => 'Admin',
            'email' => 'actions-'.$suffix.'@example.com',
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

    private function makeCustomerWithKyc(LoanProduct $product, array $overrides = []): Customer
    {
        $suffix = Str::lower(Str::random(6));

        $customer = Customer::create(array_merge([
            'company_id' => $product->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Customer',
            'last_name' => 'Review',
            'email' => 'customer-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ], $overrides));

        KycDocument::create([
            'customer_id' => $customer->id,
            'document_type' => 'nrc',
            'front_image_path' => 'kyc/front-'.$suffix.'.jpg',
            'status' => 'verified',
        ]);

        return $customer;
    }

    public function test_unapproved_customer_hides_post_approval_toolbar_actions(): void
    {
        $permissions = [
            'customers.view',
            'customers.update',
            'customers.change-group',
            'customers.reset-pin',
            'customers.send-message',
            'customers.loans',
            'customers.repayments',
            'approvals.approve',
            'approvals.reject',
            'kyc.view',
        ];
        $admin = $this->makeAdminWithPermissions($permissions);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Product',
            'code' => 'CHAR-TB-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = $this->makeCustomerWithKyc($product, [
            'status' => 'pending',
            'approval_status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer));

        $response->assertOk();
        $response->assertSeeText('Approve Customer');
        $response->assertSeeText('Reject Customer');
        $response->assertSeeText('View KYC Documents');
        $response->assertSee(route('admin.customers.edit', $customer), false);
        $response->assertDontSee(route('admin.customers.change-group', $customer), false);
        $response->assertDontSee(route('admin.customers.login-audit', $customer), false);
        $response->assertDontSee(route('admin.customers.loans', $customer), false);
        $response->assertDontSee(route('admin.customers.repayments', $customer), false);
        $response->assertDontSee('onclick="showResetPinModal()"', false);
        $response->assertDontSee('onclick="showSendMessageModal()"', false);
    }

    public function test_approved_customer_keeps_full_toolbar_actions(): void
    {
        $permissions = [
            'customers.view',
            'customers.update',
            'customers.change-group',
            'customers.reset-pin',
            'customers.send-message',
            'customers.loans',
            'customers.repayments',
            'approvals.approve',
            'approvals.reject',
            'kyc.view',
        ];
        $admin = $this->makeAdminWithPermissions($permissions);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character Product Approved',
            'code' => 'CHAR-TB-002',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = $this->makeCustomerWithKyc($product, [
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer));

        $response->assertOk();
        $response->assertDontSeeText('Approve Customer');
        $response->assertDontSeeText('Reject Customer');
        $response->assertSee(route('admin.customers.change-group', $customer), false);
        $response->assertSee(route('admin.customers.login-audit', $customer), false);
        $response->assertSee(route('admin.customers.loans', $customer), false);
        $response->assertSee(route('admin.customers.repayments', $customer), false);
        $response->assertSee('onclick="showResetPinModal()"', false);
        $response->assertSee('onclick="showSendMessageModal()"', false);
    }
}
