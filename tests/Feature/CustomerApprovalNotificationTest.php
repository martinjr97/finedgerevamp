<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\KycDocument;
use App\Models\LoanProduct;
use App\Notifications\CustomerApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Approval Co '.$suffix,
            'slug' => 'approval-co-'.$suffix,
            'code' => 'AP'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Approve',
            'last_name' => 'Admin',
            'email' => 'approve-'.$suffix.'@example.com',
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

    public function test_approving_customer_sends_approval_email_with_fresh_pin_and_logs_for_dev(): void
    {
        Notification::fake();
        Log::spy();

        $admin = $this->makeAdminWithPermissions(['approvals.approve']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Approval Product',
            'code' => 'APPROVE-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Pending',
            'last_name' => 'Customer',
            'email' => 'pending.customer@example.com',
            'phone' => '260955111222',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'must_change_pin' => false,
            'kyc_status' => 'in_review',
        ]);

        KycDocument::create([
            'customer_id' => $customer->id,
            'document_type' => 'nrc',
            'front_image_path' => 'kyc/front-test.jpg',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.approvals.customers.approve', $customer),
            ['notes' => 'Approved in QA test']
        );

        $response->assertRedirect(route('admin.approvals.index'));

        $customer->refresh();
        $this->assertSame('approved', $customer->approval_status);
        $this->assertSame('active', $customer->status);
        $this->assertSame('verified', $customer->kyc_status);
        $this->assertSame($admin->id, $customer->approved_by);
        $this->assertSame('Approved in QA test', $customer->approval_notes);
        $this->assertTrue((bool) $customer->must_change_pin);
        $this->assertFalse(Hash::check('1111', $customer->password));

        Notification::assertSentTo(
            $customer,
            CustomerApprovalNotification::class,
            function (CustomerApprovalNotification $notification) use ($customer): bool {
                return strlen($notification->pin) === 4
                    && ctype_digit($notification->pin)
                    && Hash::check($notification->pin, $customer->fresh()->password)
                    && $notification->isActive === true;
            }
        );

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function ($message, array $context) use ($customer, $admin): bool {
                return $message === 'Customer approval PIN generated'
                    && ($context['customer_id'] ?? null) === $customer->id
                    && ($context['approved_by'] ?? null) === $admin->email
                    && isset($context['new_pin'])
                    && strlen((string) $context['new_pin']) === 4;
            });
    }

    public function test_approving_customer_without_kyc_is_refused(): void
    {
        Notification::fake();

        $admin = $this->makeAdminWithPermissions(['approvals.approve']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Approval Product No KYC',
            'code' => 'APPROVE-002',
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => null,
            'loan_product_id' => $product->id,
            'first_name' => 'No',
            'last_name' => 'Kyc',
            'email' => 'no-kyc@example.com',
            'phone' => '260955111223',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'must_change_pin' => false,
            'kyc_status' => 'unverified',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.approvals.customers.approve', $customer)
        );

        $response->assertRedirect(route('admin.approvals.index'));
        $response->assertSessionHas('error', 'Customer cannot be approved before KYC documents are uploaded.');

        $customer->refresh();
        $this->assertSame('pending', $customer->approval_status);
        $this->assertNull($customer->approved_by);
        $this->assertNull($customer->approved_at);

        Notification::assertNothingSent();
    }

    public function test_company_scoped_admin_sees_pending_group_loan_customers_in_approvals_list(): void
    {
        $admin = $this->makeAdminWithPermissions(['approvals.view']);

        $groupLoanProduct = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Group Loan Product',
            'code' => 'GROUP-LIST-001',
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $visibleCustomer = Customer::create([
            'company_id' => null,
            'loan_product_id' => $groupLoanProduct->id,
            'first_name' => 'Visible',
            'last_name' => 'GroupCustomer',
            'email' => 'visible-group-customer@example.com',
            'phone' => '260955101001',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'kyc_status' => 'unverified',
        ]);

        $otherCompany = Company::create([
            'name' => 'Other Co',
            'slug' => 'other-co',
            'code' => 'OTH-CO',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $otherCompanyGroupProduct = LoanProduct::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Group Loan Product',
            'code' => 'GROUP-LIST-002',
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $hiddenCustomer = Customer::create([
            'company_id' => null,
            'loan_product_id' => $otherCompanyGroupProduct->id,
            'first_name' => 'Hidden',
            'last_name' => 'GroupCustomer',
            'email' => 'hidden-group-customer@example.com',
            'phone' => '260955101002',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'kyc_status' => 'unverified',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.approvals.index'));

        $response->assertOk();
        $response->assertSee($visibleCustomer->email);
        $response->assertDontSee($hiddenCustomer->email);
    }

    public function test_super_admin_role_bypasses_company_filter_for_pending_customer_approvals(): void
    {
        $admin = $this->makeAdminWithPermissions(['approvals.view']);

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'admin',
        ]);
        $admin->assignRole($superAdminRole);

        $otherCompany = Company::create([
            'name' => 'Cross Tenant Co',
            'slug' => 'cross-tenant-co',
            'code' => 'XTC',
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $crossCompanyProduct = LoanProduct::create([
            'company_id' => $otherCompany->id,
            'name' => 'Cross Company Group Loan Product',
            'code' => 'GROUP-LIST-003',
            'category' => 'group_loans',
            'is_active' => true,
        ]);

        $crossCompanyPendingCustomer = Customer::create([
            'company_id' => null,
            'loan_product_id' => $crossCompanyProduct->id,
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
            'email' => 'cross-tenant-customer@example.com',
            'phone' => '260955101003',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'kyc_status' => 'unverified',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.approvals.index'));

        $response->assertOk();
        $response->assertSee($crossCompanyPendingCustomer->email);
    }

    public function test_customer_show_hides_approval_buttons_when_kyc_not_uploaded(): void
    {
        $admin = $this->makeAdminWithPermissions(['approvals.approve', 'approvals.reject', 'customers.view', 'kyc.create']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Show Page Product',
            'code' => 'SHOW-001',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'No',
            'last_name' => 'KycYet',
            'email' => 'no-kyc-yet@example.com',
            'phone' => '260955111224',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'kyc_status' => 'unverified',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Please upload KYC details before they can be approved', false);
        $response->assertSee('Upload KYC Documents');
        $response->assertDontSee('Approve Customer');
        $response->assertDontSee('Reject Customer');
        $response->assertDontSee('onclick="showApproveModal');
        $response->assertDontSee('onclick="showRejectModal');
    }

    public function test_customer_show_shows_approval_buttons_when_kyc_uploaded(): void
    {
        $admin = $this->makeAdminWithPermissions(['approvals.approve', 'approvals.reject', 'customers.view']);

        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Show Page Product',
            'code' => 'SHOW-002',
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'Has',
            'last_name' => 'Kyc',
            'email' => 'has-kyc@example.com',
            'phone' => '260955111225',
            'password' => '1111',
            'status' => 'pending',
            'approval_status' => 'pending',
            'kyc_status' => 'in_review',
        ]);

        KycDocument::create([
            'customer_id' => $customer->id,
            'document_type' => 'nrc',
            'status' => 'pending',
            'front_image_path' => 'kyc/test-front.jpg',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Approve Customer');
        $response->assertSee('Reject Customer');
        $response->assertDontSee('Please upload KYC details before they can be approved', false);
    }
}
