<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanArrearsAccrual;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Services\Loans\LoanArrearsAccrualService;
use App\Services\Loans\LoanArrearsCatchUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanArrearsCatchUpPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'arrears.engine_effective_date' => '2026-01-01',
            'arrears.timezone' => 'Africa/Lusaka',
        ]);
    }

    private function adminWithPermissions(array $permissions): Admin
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Catchup Co '.$suffix,
            'slug' => 'catchup-'.$suffix,
            'code' => 'CC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Catch',
            'last_name' => 'Up',
            'email' => 'catchup-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function makeLoan(): Loan
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => 'Loan Co '.$suffix,
            'slug' => 'loan-co-'.$suffix,
            'code' => 'LC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Product',
            'code' => 'P-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260966'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
            'approval_status' => 'approved',
            'must_change_pin' => false,
        ]);

        $loan = Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 1000,
            'processing_fee' => 0,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'outstanding_balance' => 1000,
            'tenure_months' => 1,
            'loan_start_date' => '2026-01-01',
            'loan_end_date' => '2026-10-30',
            'first_payment_date' => '2026-10-30',
            'last_payment_date' => '2026-10-30',
            'accrual_type' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursed_at' => Carbon::parse('2026-01-01'),
            'arrear_rate' => 0.01,
        ]);

        LoanPaymentSchedule::create([
            'loan_id' => $loan->id,
            'period_number' => 1,
            'due_date' => '2026-10-30',
            'expected_amount' => 1000,
            'amount_paid' => 0,
            'remaining_amount' => 1000,
            'status' => 'overdue',
            'days_overdue' => 10,
        ]);

        return $loan->fresh();
    }

    public function test_prompt_shows_when_accruals_missed_and_hidden_after_dismiss(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Africa/Lusaka'));

        $loan = $this->makeLoan();
        $admin = $this->adminWithPermissions(['loans.view', 'loans.disburse']);

        $prompt = app(LoanArrearsCatchUpService::class)->buildPromptForLoan($loan);
        $this->assertNotNull($prompt);
        $this->assertTrue($prompt['has_missed_accruals']);
        $this->assertGreaterThan(0, (float) $prompt['total_arrears_charge']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertSee('Missed arrears accruals — review required');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.arrears-catchup.dismiss', $loan))
            ->assertRedirect(route('admin.loans.show', $loan));

        $loan->refresh();
        $this->assertSame('dismissed', data_get($loan->metadata, 'arrears_catchup_prompt.status'));
        $this->assertNull(app(LoanArrearsCatchUpService::class)->buildPromptForLoan($loan));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.loans.show', $loan))
            ->assertOk()
            ->assertDontSee('Missed arrears accruals — review required');
    }

    public function test_apply_posts_missed_accruals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Africa/Lusaka'));

        $loan = $this->makeLoan();
        $admin = $this->adminWithPermissions(['loans.view', 'loans.disburse']);

        $this->assertSame(0, LoanArrearsAccrual::query()->where('loan_id', $loan->id)->count());

        $this->actingAs($admin, 'admin')
            ->post(route('admin.loans.arrears-catchup.apply', $loan))
            ->assertRedirect(route('admin.loans.show', $loan));

        $this->assertGreaterThan(0, LoanArrearsAccrual::query()->where('loan_id', $loan->id)->count());
        $this->assertNull(app(LoanArrearsCatchUpService::class)->buildPromptForLoan($loan->fresh()));

        $preview = app(LoanArrearsAccrualService::class)->previewMissedAccruals($loan->fresh());
        $this->assertFalse($preview['has_missed_accruals']);
    }
}
