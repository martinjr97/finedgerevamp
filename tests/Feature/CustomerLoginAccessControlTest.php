<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerLoginAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Portal Co '.$suffix,
            'slug' => 'portal-co-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Portal Product '.$suffix,
            'code' => 'PORTAL-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        return Customer::create(array_merge([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Portal',
            'last_name' => 'Customer',
            'email' => 'portal-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ], $overrides));
    }

    public function test_login_rejects_invalid_mobile_number_format(): void
    {
        $response = $this->from(route('customer.login'))->post(route('customer.login.store'), [
            'phone' => '0978232334',
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('customer.login'));
        $response->assertSessionHasErrors('phone');
        $this->assertGuest('customer');
    }

    public function test_login_rejects_unknown_mobile_prefix(): void
    {
        $response = $this->from(route('customer.login'))->post(route('customer.login.store'), [
            'phone' => '260888123456',
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('customer.login'));
        $response->assertSessionHasErrors('phone');
        $this->assertGuest('customer');
    }

    public function test_unapproved_customer_cannot_login_from_customer_portal(): void
    {
        $customer = $this->makeCustomer([
            'approval_status' => 'pending',
            'status' => 'active',
        ]);

        $response = $this->from(route('customer.login'))->post(route('customer.login.store'), [
            'phone' => $customer->phone,
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('customer.login'));
        $response->assertSessionHas('error', 'Your account has not been approved. Please contact support.');
        $this->assertGuest('customer');
        $this->assertDatabaseHas('customer_login_audits', [
            'customer_id' => $customer->id,
            'status' => 'failed',
            'failure_reason' => 'account_not_approved',
        ]);
    }

    public function test_blocked_customer_cannot_login_from_customer_portal(): void
    {
        $customer = $this->makeCustomer([
            'approval_status' => 'approved',
            'status' => 'suspended',
        ]);

        $response = $this->from(route('customer.login'))->post(route('customer.login.store'), [
            'phone' => $customer->phone,
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('customer.login'));
        $response->assertSessionHas('error', 'Your account is blocked. Please contact support.');
        $this->assertGuest('customer');
        $this->assertDatabaseHas('customer_login_audits', [
            'customer_id' => $customer->id,
            'status' => 'failed',
            'failure_reason' => 'account_blocked',
        ]);
    }
}
