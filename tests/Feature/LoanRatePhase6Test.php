<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use App\Services\LoanRateRowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanRatePhase6Test extends TestCase
{
    use RefreshDatabase;

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Phase6 Co '.$suffix,
            'slug' => 'phase6-'.$suffix,
            'code' => 'P6'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Phase',
            'last_name' => 'Six',
            'email' => 'p6-'.$suffix.'@example.com',
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

    private function product(Admin $admin): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $admin->company_id,
            'name' => 'Test Product',
            'code' => 'TP'.Str::upper(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    public function test_create_rate_type_term_percentage_upfront_flat(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.create']);
        $product = $this->product($admin);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.store'), [
            'loan_product_id' => $product->id,
            'name' => 'Upfront Term',
            'code' => 'UPFRONT_TERM',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('loan_rate_types', [
            'code' => 'UPFRONT_TERM',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
        ]);
    }

    public function test_create_rate_type_term_percentage_daily_accrual(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.create']);
        $product = $this->product($admin);

        $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.store'), [
            'loan_product_id' => $product->id,
            'name' => 'Daily Term',
            'code' => 'DAILY_TERM',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('loan_rate_types', [
            'code' => 'DAILY_TERM',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
        ]);
    }

    public function test_create_rate_row_with_term_interest_and_processing_fee(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update', 'loan-rate-types.view']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Term Rates',
            'code' => 'TERM_RATES',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.rates.store', $rateType), [
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'arrear_rate' => 0.01,
            'is_active' => 1,
        ])->assertRedirect(route('admin.loan-rate-types.show', $rateType));

        $rate = LoanRate::where('loan_rate_type_id', $rateType->id)->first();
        $this->assertNotNull($rate);
        $this->assertEquals(27.8, (float) $rate->term_interest_percentage);
        $this->assertEquals(5.0, (float) $rate->processing_fee_percentage);
    }

    public function test_derived_daily_rate_stored_for_daily_accrual_term_percentage(): void
    {
        $pricing = app(LoanPricingService::class);
        $service = app(LoanRateRowService::class);
        $termDays = $service->previewTermDays(1);
        $expected = $pricing->calculateDerivedDailyRateFromTerm(27.8, $termDays);

        $admin = $this->adminWithPermissions(['loan-rate-types.update']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Derived',
            'code' => 'DERIVED',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.rates.store', $rateType), [
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'arrear_rate' => 0,
            'is_active' => 1,
        ]);

        $rate = LoanRate::where('loan_rate_type_id', $rateType->id)->first();
        $this->assertEquals($expected, (string) $rate->derived_daily_rate);
    }

    public function test_import_term_percentage_table(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update', 'loan-rate-types.view']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Import Term',
            'code' => 'IMPORT_TERM',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'tenure_months,processing_fee_percentage,term_interest_percentage,daily_rate,weekly_rate,min_principal,max_principal,arrear_rate,is_active',
            '1,5.00,27.8,,,,,0.01,1',
            '3,5.00,45.0,,,1000,5000,0.01,1',
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $rateType),
            ['rates_file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)]
        )->assertRedirect();

        $rates = $rateType->fresh()->loanRates()->orderBy('tenure_months')->get();
        $this->assertCount(2, $rates);
        $this->assertEquals(27.8, (float) $rates[0]->term_interest_percentage);
        $this->assertEquals(5.0, (float) $rates[0]->processing_fee_percentage);
        $this->assertNotNull($rates[0]->derived_daily_rate);
        $this->assertEquals(1000.0, (float) $rates[1]->min_principal);
        $this->assertEquals(5000.0, (float) $rates[1]->max_principal);
    }

    public function test_import_keeps_processing_fee_separate_from_interest(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Fee Sep',
            'code' => 'FEE_SEP',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $csv = "tenure_months,processing_fee_percentage,term_interest_percentage,arrear_rate,is_active\n1,5.00,27.8,0,1\n";
        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $rateType),
            ['rates_file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)]
        );

        $rate = $rateType->loanRates()->first();
        $this->assertEquals(5.0, (float) $rate->processing_fee_percentage);
        $this->assertEquals(27.8, (float) $rate->term_interest_percentage);
        $this->assertNull($rate->derived_daily_rate);
    }

    public function test_import_daily_multiplier_legacy_still_works(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Legacy Daily',
            'code' => 'LEG_DAILY',
            'accrual_period' => 'daily',
            'rate_input_mode' => LoanRateType::RATE_INPUT_DAILY_MULTIPLIER,
            'is_active' => true,
        ]);

        $csv = "tenure_months,processing_fee_percentage,daily_rate,arrear_rate,is_active\n1,5.00,0.03,0.01,1\n";
        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $rateType),
            ['rates_file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)]
        )->assertRedirect();

        $rate = $rateType->loanRates()->first();
        $this->assertEquals(0.03, (float) $rate->daily_rate);
        $this->assertNull($rate->term_interest_percentage);
    }

    public function test_import_weekly_multiplier_legacy_still_works(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Legacy Weekly',
            'code' => 'LEG_WEEK',
            'accrual_period' => 'weekly',
            'rate_input_mode' => LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
            'is_active' => true,
        ]);

        $csv = "tenure_months,processing_fee_percentage,weekly_rate,arrear_rate,is_active\n2,6.00,0.05,0.01,1\n";
        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $rateType),
            ['rates_file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)]
        )->assertRedirect();

        $rate = $rateType->loanRates()->first();
        $this->assertEquals(0.05, (float) $rate->weekly_rate);
    }

    public function test_import_validation_fails_when_required_rate_field_missing(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Validate',
            'code' => 'VALIDATE',
            'accrual_period' => 'daily',
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $csv = "tenure_months,processing_fee_percentage,term_interest_percentage,arrear_rate,is_active\n1,5.00,,0,1\n";
        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $rateType),
            ['rates_file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertCount(0, $rateType->loanRates);
    }

    public function test_copy_rate_type_preserves_new_fields(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update', 'loan-rate-types.view']);
        $sourceProduct = $this->product($admin);
        $targetProduct = $this->product($admin);

        $source = LoanRateType::create([
            'loan_product_id' => $sourceProduct->id,
            'name' => 'Source',
            'code' => 'SRC_NEW',
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $source->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'min_principal' => 1000,
            'max_principal' => 5000,
            'derived_daily_rate' => '0.00926667',
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.copy-product', $source),
            ['target_loan_product_id' => $targetProduct->id]
        )->assertRedirect();

        $copied = LoanRateType::where('loan_product_id', $targetProduct->id)->latest('id')->first();
        $this->assertSame(LoanRateType::RATE_INPUT_TERM_PERCENTAGE, $copied->rate_input_mode);
        $this->assertSame(LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL, $copied->interest_behavior);

        $copiedRate = LoanRate::where('loan_rate_type_id', $copied->id)->first();
        $this->assertEquals(27.8, (float) $copiedRate->term_interest_percentage);
        $this->assertEquals(1000.0, (float) $copiedRate->min_principal);
        $this->assertEquals(5000.0, (float) $copiedRate->max_principal);
        $this->assertNotNull($copiedRate->derived_daily_rate);
    }

    public function test_amount_band_rows_allowed_per_tenure(): void
    {
        $admin = $this->adminWithPermissions(['loan-rate-types.update']);
        $product = $this->product($admin);
        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Bands',
            'code' => 'BANDS',
            'accrual_period' => 'daily',
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.rates.store', $rateType), [
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 20,
            'arrear_rate' => 0,
            'is_active' => 1,
        ])->assertRedirect();

        $this->actingAs($admin, 'admin')->post(route('admin.loan-rate-types.rates.store', $rateType), [
            'tenure_months' => 1,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'min_principal' => 1000,
            'max_principal' => 5000,
            'arrear_rate' => 0,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertCount(2, LoanRate::where('loan_rate_type_id', $rateType->id)->where('tenure_months', 1)->get());
    }
}
