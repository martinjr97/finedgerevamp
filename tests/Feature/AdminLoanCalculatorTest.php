<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminLoanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: Admin, product: LoanProduct, group: CustomerGroup, rateType: LoanRateType, lowBandRate: LoanRate, highBandRate: LoanRate}
     */
    private function createCalculatorContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Calc Co '.$suffix,
            'slug' => 'calc-co-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Calc',
            'last_name' => 'Admin',
            'email' => 'calc-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        Permission::firstOrCreate(['name' => 'loans.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo('loans.view');

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Calculator Product',
            'code' => 'CP-'.$suffix,
            'category' => 'character',
            'accrual_type' => 'at_beginning',
            'max_amount' => 100000,
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Standard Rates',
            'code' => 'STD-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $lowBandRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 15,
            'min_principal' => 1000,
            'max_principal' => 5000,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $highBandRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 20,
            'min_principal' => 5001,
            'max_principal' => 50000,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 6,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 25,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Retail Group',
            'code' => 'RET-'.$suffix,
            'risk_level' => 'medium',
            'max_loan_amount' => 50000,
            'max_loan_tenure_months' => 12,
            'is_active' => true,
        ]);

        return compact('admin', 'product', 'group', 'rateType', 'lowBandRate', 'highBandRate');
    }

    public function test_groups_endpoint_returns_rate_card_metadata_for_product(): void
    {
        $context = $this->createCalculatorContext();

        $response = $this->actingAs($context['admin'], 'admin')
            ->getJson(route('admin.loan-calculator.groups', [
                'loan_product_id' => $context['product']->id,
            ]));

        $response->assertOk();
        $response->assertJsonPath('groups.0.name', 'Retail Group');
        $response->assertJsonPath('groups.0.rate_type_name', 'Standard Rates');
        $response->assertJsonPath('groups.0.interest_behavior', LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT);
        $response->assertJsonFragment(['available_tenures' => [3, 6]]);
    }

    public function test_calculate_uses_amount_band_for_matching_tenure_only_once(): void
    {
        $context = $this->createCalculatorContext();

        $response = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-calculator.calculate'), [
                'loan_product_id' => $context['product']->id,
                'customer_group_id' => $context['group']->id,
                'amount' => 10000,
                'start_date' => '2026-01-01',
            ]);

        $response->assertOk();

        $threeMonthRows = collect($response->json('rows'))
            ->where('tenure_months', 3)
            ->values();

        $this->assertCount(1, $threeMonthRows);
        $this->assertSame($context['highBandRate']->id, $threeMonthRows->first()['loan_rate_id']);
        $this->assertEqualsWithDelta(2000.0, (float) $threeMonthRows->first()['interest'], 0.01);

        $sixMonthRow = collect($response->json('rows'))->firstWhere('tenure_months', 6);
        $this->assertNotNull($sixMonthRow);
        $this->assertEqualsWithDelta(2500.0, (float) $sixMonthRow['interest'], 0.01);
    }

    public function test_calculate_rejects_group_not_under_selected_product(): void
    {
        $context = $this->createCalculatorContext();

        $otherProduct = LoanProduct::create([
            'company_id' => $context['product']->company_id,
            'name' => 'Other Product',
            'code' => 'OTH-'.Str::lower(Str::random(4)),
            'category' => 'character',
            'is_active' => true,
        ]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-calculator.calculate'), [
                'loan_product_id' => $otherProduct->id,
                'customer_group_id' => $context['group']->id,
                'amount' => 5000,
            ]);

        $response->assertNotFound();
    }

    public function test_calculate_returns_error_when_amount_exceeds_limits(): void
    {
        $context = $this->createCalculatorContext();

        $response = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-calculator.calculate'), [
                'loan_product_id' => $context['product']->id,
                'customer_group_id' => $context['group']->id,
                'amount' => 75000,
                'start_date' => '2026-01-01',
            ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Amount exceeds this group/product limit of 50,000.00']);
    }

    public function test_calculate_returns_all_rate_tenures_even_when_group_max_tenure_is_lower(): void
    {
        $context = $this->createCalculatorContext();

        $context['group']->update(['max_loan_tenure_months' => 2]);

        $response = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-calculator.calculate'), [
                'loan_product_id' => $context['product']->id,
                'customer_group_id' => $context['group']->id,
                'amount' => 10000,
                'start_date' => '2026-01-01',
            ]);

        $response->assertOk();
        $tenures = collect($response->json('rows'))->pluck('tenure_months')->all();

        $this->assertContains(3, $tenures);
        $this->assertContains(6, $tenures);

        $aboveLimit = collect($response->json('rows'))->where('exceeds_group_max_tenure', true);
        $this->assertGreaterThan(0, $aboveLimit->count());
    }

    public function test_calculator_index_page_loads(): void
    {
        $context = $this->createCalculatorContext();

        $response = $this->actingAs($context['admin'], 'admin')
            ->get(route('admin.loan-calculator.index'));

        $response->assertOk();
        $response->assertSee('Loan Calculator', false);
        $response->assertSee('How this works', false);
        $response->assertSee('Calculator Product', false);
    }
}
