<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanRateTypeSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanRateTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Mgmt Co '.$suffix,
            'slug' => 'mgmt-'.$suffix,
            'code' => 'MG'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Mgmt',
            'last_name' => 'Admin',
            'email' => 'mgmt-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function product(Admin $admin, string $code = 'P1'): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Product '.$code,
            'code' => $code,
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    private function rateType(LoanProduct $product, array $overrides = []): LoanRateType
    {
        return LoanRateType::create(array_merge([
            'loan_product_id' => $product->id,
            'name' => 'Standard',
            'code' => 'STD_'.Str::upper(Str::random(4)),
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ], $overrides));
    }

    private function rateRow(LoanRateType $type, array $overrides = []): LoanRate
    {
        return LoanRate::create(array_merge([
            'loan_rate_type_id' => $type->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'derived_daily_rate' => 0.00926667,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ], $overrides));
    }

    public function test_create_rate_type_defaults_to_term_percentage_and_upfront_flat(): void
    {
        $admin = $this->admin(['loan-rate-types.create']);
        $product = $this->product($admin);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.store'), [
            'loan_product_id' => $product->id,
            'name' => 'Defaults Plan',
            'code' => 'DEFAULTS_PLAN',
            'is_active' => 1,
        ]);

        $rateType = LoanRateType::where('code', 'DEFAULTS_PLAN')->firstOrFail();
        $response->assertRedirect(route('admin.loan-rate-types.show', $rateType));

        $this->assertDatabaseHas('loan_rate_types', [
            'code' => 'DEFAULTS_PLAN',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'accrual_period' => 'daily',
        ]);
    }

    public function test_accrual_period_derived_without_ui_input(): void
    {
        $admin = $this->admin(['loan-rate-types.create']);
        $product = $this->product($admin);

        $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.store'), [
            'loan_product_id' => $product->id,
            'name' => 'Weekly Legacy',
            'code' => 'WEEKLY_LEGACY',
            'rate_input_mode' => LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('loan_rate_types', [
            'code' => 'WEEKLY_LEGACY',
            'accrual_period' => 'weekly',
            'rate_input_mode' => LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
        ]);
    }

    public function test_delete_unused_loan_rate_row_succeeds(): void
    {
        $admin = $this->admin(['loan-rate-types.delete', 'loan-rate-types.view']);
        $type = $this->rateType($this->product($admin));
        $rate = $this->rateRow($type);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.loan-rate-types.rates.destroy', [$type, $rate]))
            ->assertRedirect(route('admin.loan-rate-types.show', $type));

        $this->assertSoftDeleted('loan_rates', ['id' => $rate->id]);
    }

    public function test_delete_used_loan_rate_row_is_blocked(): void
    {
        $admin = $this->admin(['loan-rate-types.delete', 'loan-rate-types.view']);
        $product = $this->product($admin);
        $type = $this->rateType($product);
        $rate = $this->rateRow($type);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'T',
            'last_name' => 'U',
            'email' => 'tu-'.Str::random(4).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_rate_id' => $rate->id,
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
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.loan-rate-types.rates.destroy', [$type, $rate]))
            ->assertRedirect(route('admin.loan-rate-types.show', $type))
            ->assertSessionHas('error', LoanRateTypeSafetyService::RATE_IN_USE_MESSAGE);

        $this->assertDatabaseHas('loan_rates', ['id' => $rate->id, 'deleted_at' => null]);
    }

    public function test_delete_unused_rate_type_succeeds_and_deletes_child_rows(): void
    {
        $admin = $this->admin(['loan-rate-types.delete', 'loan-rate-types.view']);
        $type = $this->rateType($this->product($admin));
        $rate = $this->rateRow($type);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.loan-rate-types.destroy', $type))
            ->assertRedirect(route('admin.loan-rate-types.index'));

        $this->assertSoftDeleted('loan_rate_types', ['id' => $type->id]);
        $this->assertSoftDeleted('loan_rates', ['id' => $rate->id]);
    }

    public function test_delete_rate_type_assigned_to_customer_group_is_blocked(): void
    {
        $admin = $this->admin(['loan-rate-types.delete', 'loan-rate-types.view']);
        $product = $this->product($admin);
        $type = $this->rateType($product);

        CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $type->id,
            'name' => 'Group A',
            'code' => 'GA-'.Str::random(4),
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.loan-rate-types.destroy', $type))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('loan_rate_types', ['id' => $type->id, 'deleted_at' => null]);
    }

    public function test_delete_rate_type_with_used_rate_rows_is_blocked(): void
    {
        $admin = $this->admin(['loan-rate-types.delete', 'loan-rate-types.view']);
        $product = $this->product($admin);
        $type = $this->rateType($product);
        $rate = $this->rateRow($type);

        $customer = Customer::create([
            'company_id' => $admin->company_id,
            'loan_product_id' => $product->id,
            'first_name' => 'B',
            'last_name' => 'R',
            'email' => 'br-'.Str::random(4).'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_rate_id' => $rate->id,
            'loan_number' => 'LN2-'.Str::upper(Str::random(6)),
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
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.loan-rate-types.destroy', $type))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('loan_rate_types', ['id' => $type->id, 'deleted_at' => null]);
    }

    public function test_copy_rate_type_to_another_product_copies_pricing_fields_and_rows(): void
    {
        $admin = $this->admin(['loan-rate-types.update', 'loan-rate-types.view']);
        $sourceProduct = $this->product($admin, 'SRC');
        $targetProduct = $this->product($admin, 'TGT');

        $source = $this->rateType($sourceProduct, [
            'name' => 'Copy Me',
            'code' => 'COPY_SRC',
            'description' => 'Source desc',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
        ]);

        $this->rateRow($source, [
            'tenure_months' => 3,
            'processing_fee_percentage' => 4.5,
            'term_interest_percentage' => 30.5,
            'min_principal' => 1000,
            'max_principal' => 50000,
            'derived_daily_rate' => 0.00338,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-rate-types.copy-product', $source), [
                'target_loan_product_id' => $targetProduct->id,
            ])
            ->assertRedirect();

        $copied = LoanRateType::where('loan_product_id', $targetProduct->id)
            ->where('name', 'Copy Me')
            ->first();

        $this->assertNotNull($copied);
        $this->assertNotSame($source->id, $copied->id);
        $this->assertSame(LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT, $copied->interest_behavior);
        $this->assertSame(LoanRateType::RATE_INPUT_TERM_PERCENTAGE, $copied->rate_input_mode);

        $copiedRate = LoanRate::where('loan_rate_type_id', $copied->id)->first();
        $this->assertNotNull($copiedRate);
        $this->assertSame(3, $copiedRate->tenure_months);
        $this->assertEquals(4.5, (float) $copiedRate->processing_fee_percentage);
        $this->assertEquals(30.5, (float) $copiedRate->term_interest_percentage);
        $this->assertEquals(1000.0, (float) $copiedRate->min_principal);
        $this->assertEquals(50000.0, (float) $copiedRate->max_principal);
    }

    public function test_copied_rate_type_is_independent_from_original(): void
    {
        $admin = $this->admin(['loan-rate-types.update', 'loan-rate-types.view']);
        $sourceProduct = $this->product($admin, 'S2');
        $targetProduct = $this->product($admin, 'T2');
        $source = $this->rateType($sourceProduct, ['name' => 'Independent', 'code' => 'IND_SRC']);
        $this->rateRow($source, ['term_interest_percentage' => 20]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loan-rate-types.copy-product', $source), [
                'target_loan_product_id' => $targetProduct->id,
            ]);

        $copied = LoanRateType::where('loan_product_id', $targetProduct->id)->latest('id')->first();
        LoanRate::where('loan_rate_type_id', $copied->id)->update(['term_interest_percentage' => 99]);

        $this->assertEquals(20.0, (float) LoanRate::where('loan_rate_type_id', $source->id)->value('term_interest_percentage'));
        $this->assertEquals(99.0, (float) LoanRate::where('loan_rate_type_id', $copied->id)->value('term_interest_percentage'));
    }

    public function test_create_form_does_not_expose_legacy_rate_entry_options(): void
    {
        $admin = $this->admin(['loan-rate-types.create']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loan-rate-types.create'))
            ->assertOk()
            ->assertSee('Interest Behavior', false)
            ->assertDontSee('Legacy / advanced', false)
            ->assertDontSee('Legacy daily rate multiplier', false)
            ->assertDontSee('Legacy weekly rate multiplier', false)
            ->assertDontSee('Rate Entry Method', false);
    }
}
