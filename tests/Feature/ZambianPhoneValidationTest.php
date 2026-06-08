<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Ministry;
use App\Support\ZambianPhoneRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ZambianPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PHONE = '260978232334';

    private function admin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'Phone Co '.$suffix,
            'slug' => 'phone-'.$suffix,
            'code' => 'PC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Phone',
            'last_name' => 'Admin',
            'email' => 'phone-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_customer_creation_rejects_local_zero_prefix_format(): void
    {
        $admin = $this->admin(['customers.create']);
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Gov',
            'code' => 'GOV-'.Str::upper(Str::random(3)),
            'category' => 'government',
            'is_active' => true,
        ]);
        $ministry = Ministry::create([
            'name' => 'Health',
            'code' => 'MOH-'.Str::random(3),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.store'), [
                'loan_product_id' => $product->id,
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'user-'.Str::random(5).'@example.com',
                'phone' => '0978232334',
                'national_id_type' => 'nrc',
                'national_id' => '123456/78/1',
                'tpin' => 'TPIN'.Str::random(6),
                'address_line1' => 'Line 1',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'ministry_id' => $ministry->id,
                'date_of_employment' => '2020-01-01',
                'gross_salary' => 10000,
                'net_salary' => 8000,
                'verified_by' => $admin->id,
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_customer_creation_accepts_260_format(): void
    {
        $errors = Validator::make(
            ['phone' => self::VALID_PHONE],
            ['phone' => ZambianPhoneRules::nullable()]
        )->errors();

        $this->assertTrue($errors->isEmpty());
    }

    public function test_customer_update_validates_nullable_phone_fields(): void
    {
        $empty = Validator::make(['phone' => null], ['phone' => ZambianPhoneRules::nullable()]);
        $this->assertTrue($empty->passes());

        $invalid = Validator::make(['phone' => '0978232334'], ['phone' => ZambianPhoneRules::nullable()]);
        $this->assertTrue($invalid->fails());
    }

    public function test_disbursement_phone_rejects_invalid_format(): void
    {
        $admin = $this->admin(['loans.update-payment-details']);
        $context = $this->loanContext($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.payment-details', $context['loan']), [
                'channel_id' => $context['channel']->id,
                'disbursement_phone_number' => '0978232334',
            ])
            ->assertSessionHasErrors('disbursement_phone_number');
    }

    public function test_disbursement_phone_accepts_valid_format(): void
    {
        $admin = $this->admin(['loans.update-payment-details']);
        $context = $this->loanContext($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.payment-details', $context['loan']), [
                'channel_id' => $context['channel']->id,
                'disbursement_phone_number' => self::VALID_PHONE,
            ])
            ->assertSessionDoesntHaveErrors('disbursement_phone_number');
    }

    public function test_nullable_phone_field_can_be_empty(): void
    {
        $result = Validator::make(['phone' => ''], ['phone' => ZambianPhoneRules::nullable()]);

        $this->assertTrue($result->passes());
    }

    /**
     * @return array{loan: Loan, channel: Channel}
     */
    private function loanContext(Admin $admin): array
    {
        $product = LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Character',
            'code' => 'CHR-'.Str::upper(Str::random(3)),
            'category' => 'character',
            'is_active' => true,
        ]);
        $channel = Channel::create([
            'name' => 'MTN',
            'code' => 'MTN-'.Str::random(4),
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'L',
            'last_name' => 'N',
            'email' => 'ln-'.Str::random(4).'@example.com',
            'phone' => self::VALID_PHONE,
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
        ]);
        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => 'LN-'.Str::upper(Str::random(6)),
            'principal_amount' => 1000,
            'processing_fee' => 50,
            'interest_accrued' => 200,
            'total_amount' => 1250,
            'outstanding_balance' => 1250,
            'amount_paid' => 0,
            'tenure_months' => 1,
            'loan_start_date' => '2026-01-01',
            'loan_end_date' => '2026-02-01',
            'first_payment_date' => '2026-02-01',
            'last_payment_date' => '2026-02-01',
            'accrual_type' => 'at_beginning',
            'accrual_period' => 'daily',
            'status' => 'pending_approval',
            'disbursement_status' => 'pending',
            'disbursement_phone_number' => self::VALID_PHONE,
        ]);

        return ['loan' => $loan, 'channel' => $channel];
    }
}
