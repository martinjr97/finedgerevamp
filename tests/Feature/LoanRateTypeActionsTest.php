<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanRateTypeActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Rates Test Co '.$suffix,
            'slug' => 'rates-test-co-'.$suffix,
            'code' => 'RTC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Rate',
            'last_name' => 'Admin',
            'email' => 'rates-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'admin',
            ]);
        }

        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function makeLoanProduct(Company $company, string $name, string $code): LoanProduct
    {
        return LoanProduct::create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'category' => 'character',
            'is_active' => true,
        ]);
    }

    public function test_import_rates_from_file_creates_and_updates_records(): void
    {
        $admin = $this->makeAdminWithPermissions(['loan-rate-types.update', 'loan-rate-types.view']);
        $product = $this->makeLoanProduct($admin->company, 'Character Loan A', 'CHAR-A');

        $loanRateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Standard Rates',
            'code' => 'STANDARD_RATES',
            'description' => 'Daily rate plan',
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $loanRateType->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => 3.00,
            'daily_rate' => 0.01000,
            'weekly_rate' => null,
            'arrear_rate' => 0.00500,
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'tenure_months,processing_fee_percentage,daily_rate,arrear_rate,is_active',
            '1,5.00,0.02000,0.01000,1',
            '2,7.50,0.03000,0.01500,0',
        ]);

        $file = UploadedFile::fake()->createWithContent('loan-rates.csv', $csv);

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.rates.import', $loanRateType),
            ['rates_file' => $file]
        );

        $response->assertRedirect(route('admin.loan-rate-types.show', $loanRateType));

        $loanRateType->refresh();
        $rates = $loanRateType->loanRates()->orderBy('tenure_months')->get();

        $this->assertCount(2, $rates);

        $tenureOne = $rates->firstWhere('tenure_months', 1);
        $tenureTwo = $rates->firstWhere('tenure_months', 2);

        $this->assertNotNull($tenureOne);
        $this->assertNotNull($tenureTwo);

        $this->assertEquals(5.00, (float) $tenureOne->processing_fee_percentage);
        $this->assertEquals(0.02000, (float) $tenureOne->daily_rate);
        $this->assertEquals(0.01000, (float) $tenureOne->arrear_rate);
        $this->assertTrue($tenureOne->is_active);

        $this->assertEquals(7.50, (float) $tenureTwo->processing_fee_percentage);
        $this->assertEquals(0.03000, (float) $tenureTwo->daily_rate);
        $this->assertEquals(0.01500, (float) $tenureTwo->arrear_rate);
        $this->assertFalse($tenureTwo->is_active);
    }

    public function test_copy_to_product_creates_new_rate_type_with_cloned_rates(): void
    {
        $admin = $this->makeAdminWithPermissions(['loan-rate-types.update', 'loan-rate-types.view']);
        $sourceProduct = $this->makeLoanProduct($admin->company, 'Character Loan Source', 'CHAR-SRC');
        $targetProduct = $this->makeLoanProduct($admin->company, 'Character Loan Target', 'CHAR-TGT');

        $sourceRateType = LoanRateType::create([
            'loan_product_id' => $sourceProduct->id,
            'name' => 'Source Rate Type',
            'code' => 'SRC_RATE_TYPE',
            'description' => 'Source description',
            'accrual_period' => 'daily',
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $sourceRateType->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => 2.00,
            'daily_rate' => 0.01000,
            'weekly_rate' => null,
            'arrear_rate' => 0.00500,
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $sourceRateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 4.00,
            'daily_rate' => 0.01250,
            'weekly_rate' => null,
            'arrear_rate' => 0.00600,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.loan-rate-types.copy-product', $sourceRateType),
            ['target_loan_product_id' => $targetProduct->id]
        );

        $response->assertRedirect();

        $copiedRateType = LoanRateType::query()
            ->where('loan_product_id', $targetProduct->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($copiedRateType);
        $this->assertSame($sourceRateType->name, $copiedRateType->name);
        $this->assertSame($sourceRateType->accrual_period, $copiedRateType->accrual_period);
        $this->assertNotSame($sourceRateType->id, $copiedRateType->id);
        $this->assertNotSame($sourceRateType->code, $copiedRateType->code);

        $copiedRates = LoanRate::query()
            ->where('loan_rate_type_id', $copiedRateType->id)
            ->orderBy('tenure_months')
            ->get();

        $this->assertCount(2, $copiedRates);
        $this->assertSame([1, 3], $copiedRates->pluck('tenure_months')->all());
        $this->assertEquals(2.00, (float) $copiedRates[0]->processing_fee_percentage);
        $this->assertEquals(4.00, (float) $copiedRates[1]->processing_fee_percentage);
        $this->assertTrue($copiedRates[0]->is_active);
        $this->assertFalse($copiedRates[1]->is_active);
    }
}

