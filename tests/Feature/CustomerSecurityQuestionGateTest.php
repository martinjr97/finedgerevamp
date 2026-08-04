<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\SecurityQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSecurityQuestionGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Security Gate Co '.$suffix,
            'slug' => 'security-gate-co-'.$suffix,
            'code' => 'SGC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $loanProduct = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Security Gate Product '.$suffix,
            'code' => 'SGP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        return Customer::create(array_merge([
            'company_id' => $company->id,
            'loan_product_id' => $loanProduct->id,
            'first_name' => 'Gate',
            'last_name' => 'Customer',
            'email' => 'security-gate-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ], $overrides));
    }

    public function test_dashboard_redirects_when_security_question_missing(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));

        $response->assertRedirect(route('customer.security-questions.setup'));
        $response->assertSessionHas('status', 'Please set a security question before continuing.');
    }

    public function test_customer_can_access_dashboard_after_setting_security_question(): void
    {
        $customer = $this->withCustomerSecurityQuestion($this->makeCustomer());

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));

        $response->assertOk();
    }

    public function test_setup_and_store_routes_remain_accessible_without_security_question(): void
    {
        $customer = $this->makeCustomer();
        $question = SecurityQuestion::query()->create([
            'question' => 'What is your favorite meal?',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('customer.security-questions.setup'))
            ->assertOk();

        $response = $this->actingAs($customer, 'customer')
            ->post(route('customer.security-questions.store'), [
                'security_question_id' => $question->id,
                'security_answer' => 'Nshima',
            ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertTrue($customer->fresh()->hasSecurityQuestionConfigured());
        $this->assertSame('nshima', $customer->fresh()->security_answer);
    }

    public function test_security_answer_validation_is_case_insensitive(): void
    {
        $customer = $this->makeCustomer([
            'national_id' => '123456/78/1',
        ]);
        $question = SecurityQuestion::query()->create([
            'question' => 'In what town or city were you born?',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.security-questions.store'), [
                'security_question_id' => $question->id,
                'security_answer' => 'Zambia',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $customer = $customer->fresh();
        $this->assertSame('zambia', $customer->security_answer);
        $this->assertTrue($customer->matchesSecurityAnswer('zambia'));
        $this->assertTrue($customer->matchesSecurityAnswer('ZAMBIA'));
        $this->assertTrue($customer->matchesSecurityAnswer(' Zambia '));
        $this->assertFalse($customer->matchesSecurityAnswer('Kenya'));
    }

    public function test_login_redirects_to_security_question_setup_when_missing(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->post(route('customer.login.store'), [
            'phone' => $customer->phone,
            'pin' => '1234',
        ]);

        $response->assertRedirect(route('customer.security-questions.setup'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
