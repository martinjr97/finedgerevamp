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
use App\Services\LoanPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanPricingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private LoanPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(LoanPricingService::class);
    }

    public function test_admin_character_loan_upfront_flat_term_percentage_books_full_interest(): void
    {
        $context = $this->createCharacterApplicationContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
        );

        $quote = $this->postAdminCalculateRepayment($context, 10000, 1, '2026-01-01');

        $this->assertEqualsWithDelta(2780.0, $quote['interest'], 0.01);
        $this->assertEqualsWithDelta(13280.0, $quote['booked_total_amount'], 0.01);
        $this->assertSame('at_beginning', $quote['accrual_type']);

        $loan = $this->createAdminCharacterLoan($context, $quote);

        $this->assertEqualsWithDelta(2780.0, (float) $loan->interest_accrued, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $loan->processing_fee, 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $loan->outstanding_balance, 0.01);
        $this->assertSame('at_beginning', $loan->accrual_type);
        $this->assertSame(Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT, $loan->interest_behavior);
    }

    public function test_admin_character_loan_daily_accrual_term_percentage_books_principal_and_fee_only(): void
    {
        $context = $this->createCharacterApplicationContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
        );

        $quote = $this->postAdminCalculateRepayment($context, 10000, 1, '2026-01-01');

        $this->assertEqualsWithDelta(2780.0, $quote['projected_interest'], 0.01);
        $this->assertEqualsWithDelta(10500.0, $quote['booked_total_amount'], 0.01);
        $this->assertEqualsWithDelta(0.0, $quote['booked_interest_accrued'], 0.01);
        $this->assertSame('daily', $quote['accrual_type']);
        $this->assertNotNull($quote['derived_daily_rate']);

        $loan = $this->createAdminCharacterLoan($context, $quote);

        $this->assertEqualsWithDelta(0.0, (float) $loan->interest_accrued, 0.01);
        $this->assertEqualsWithDelta(10500.0, (float) $loan->outstanding_balance, 0.01);
        $this->assertSame('daily', $loan->accrual_type);
        $this->assertNotNull($loan->daily_rate);
    }

    public function test_customer_calculator_matches_customer_loan_store(): void
    {
        $context = $this->createCustomerSelfServiceContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
        );

        $this->actingAs($context['customer'], 'customer')
            ->withSession([
                'loan_application.channel_id' => $context['channel']->id,
                'loan_application.amount' => 10000,
                'loan_application.destination_validated' => true,
                'loan_application.disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
                'loan_application.disbursement_phone_number' => $context['customer']->phone,
                'loan_application.phone_number' => $context['customer']->phone,
            ])
            ->post(route('customer.loans.calculate.store'), ['tenure_months' => 1])
            ->assertOk();

        $bookedTotal = session('loan_application.total_amount');
        $interest = session('loan_application.interest');

        config(['approval.loans.create' => false]);

        $sessionData = [
            'loan_application.channel_id' => $context['channel']->id,
            'loan_application.amount' => 10000,
            'loan_application.destination_validated' => true,
            'loan_application.disbursement_channel_type' => Channel::TYPE_MOBILE_WALLET,
            'loan_application.disbursement_phone_number' => $context['customer']->phone,
            'loan_application.phone_number' => $context['customer']->phone,
            'loan_application.tenure_months' => 1,
            'loan_application.loan_rate_id' => $context['loanRate']->id,
            'loan_application.loan_start_date' => session('loan_application.loan_start_date'),
            'loan_application.loan_end_date' => session('loan_application.loan_end_date'),
            'loan_application.processing_fee' => session('loan_application.processing_fee'),
            'loan_application.interest' => session('loan_application.interest'),
            'loan_application.total_amount' => session('loan_application.total_amount'),
            'loan_application.monthly_payment' => session('loan_application.monthly_payment'),
        ];

        $this->actingAs($context['customer'], 'customer')
            ->withSession($sessionData)
            ->post(route('customer.loans.store'))
            ->assertRedirect(route('customer.dashboard'));

        $loan = Loan::query()->latest('id')->first();
        $this->assertNotNull($loan);
        $this->assertEqualsWithDelta((float) $bookedTotal, (float) $loan->total_amount, 0.01);
        $this->assertEqualsWithDelta((float) $interest, (float) $loan->interest_accrued, 0.01);
    }

    public function test_admin_calculator_matches_admin_loan_creation(): void
    {
        $context = $this->createCharacterApplicationContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
        );

        $calcResponse = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-calculator.calculate'), [
                'loan_product_id' => $context['product']->id,
                'customer_group_id' => $context['group']->id,
                'amount' => 10000,
                'start_date' => '2026-01-01',
            ]);

        $calcResponse->assertOk();
        $row = collect($calcResponse->json('rows'))->firstWhere('tenure_months', 1);
        $this->assertNotNull($row);

        $quote = $this->postAdminCalculateRepayment($context, 10000, 1, '2026-01-01');
        $loan = $this->createAdminCharacterLoan($context, $quote);

        $this->assertEqualsWithDelta((float) $row['total'], (float) $loan->total_amount, 0.01);
        $this->assertEqualsWithDelta((float) $row['interest'], (float) $loan->interest_accrued, 0.01);
    }

    public function test_cron_does_not_accrue_upfront_flat_loans(): void
    {
        $loan = $this->createActiveLoanForCron(
            accrualType: 'at_beginning',
            interestBehavior: Loan::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            interestAccrued: 2780,
            dailyRate: null,
        );

        $this->artisan('loans:accrue-interest', ['--date' => $loan->loan_start_date->toDateString()])
            ->assertSuccessful();

        $this->assertSame(0, $loan->accruals()->count());
        $loan->refresh();
        $this->assertEqualsWithDelta(2780.0, (float) $loan->interest_accrued, 0.01);
    }

    public function test_cron_accrues_daily_loan_once_per_date(): void
    {
        $loan = $this->createActiveLoanForCron(
            accrualType: 'daily',
            interestBehavior: Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            interestAccrued: 0,
            dailyRate: '0.00926667',
            processingFee: 500,
            totalAmount: 10500,
            outstandingBalance: 10500,
        );

        $date = Carbon::parse($loan->loan_start_date);

        $this->artisan('loans:accrue-interest', ['--date' => $date->toDateString()])
            ->assertSuccessful();

        $loan->refresh();
        $this->assertSame(1, $loan->accruals()->count());

        $this->artisan('loans:accrue-interest', ['--date' => $date->toDateString()])
            ->assertSuccessful();

        $loan->refresh();
        $this->assertSame(1, $loan->accruals()->count());

        $nextDate = $date->copy()->addDay();
        $this->artisan('loans:accrue-interest', ['--date' => $nextDate->toDateString()])
            ->assertSuccessful();

        $loan->refresh();
        $this->assertSame(2, $loan->accruals()->count());
    }

    public function test_legacy_daily_multiplier_books_no_upfront_interest(): void
    {
        $context = $this->createCharacterApplicationContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            rateInputMode: LoanRateType::RATE_INPUT_DAILY_MULTIPLIER,
            dailyRate: 0.01,
            productAccrualType: 'daily',
            processingFeePercentage: 5,
        );

        $quote = $this->postAdminCalculateRepayment($context, 10000, 1, '2026-01-01');

        $this->assertEqualsWithDelta(3100.0, $quote['projected_interest'], 0.01);
        $this->assertEqualsWithDelta(10500.0, $quote['booked_total_amount'], 0.01);
        $this->assertSame('daily', $quote['accrual_type']);

        $loan = $this->createAdminCharacterLoan($context, $quote);
        $this->assertEqualsWithDelta(0.0, (float) $loan->interest_accrued, 0.01);
        $this->assertEqualsWithDelta(10500.0, (float) $loan->outstanding_balance, 0.01);
    }

    public function test_legacy_weekly_multiplier_upfront_books_full_interest(): void
    {
        $context = $this->createCharacterApplicationContext(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_WEEKLY_MULTIPLIER,
            weeklyRate: 0.05,
            productAccrualType: 'at_beginning',
            processingFeePercentage: 5,
        );

        $quote = $this->postAdminCalculateRepayment($context, 10000, 1, '2026-01-01');

        $this->assertGreaterThan(0, $quote['interest']);
        $this->assertSame('at_beginning', $quote['accrual_type']);

        $loan = $this->createAdminCharacterLoan($context, $quote);
        $this->assertGreaterThan(0, (float) $loan->interest_accrued);
        $this->assertSame('at_beginning', $loan->accrual_type);
    }

    /**
     * @return array<string, mixed>
     */
    private function createCharacterApplicationContext(
        string $interestBehavior,
        string $rateInputMode,
        ?float $termInterestPercentage = null,
        ?float $dailyRate = null,
        ?float $weeklyRate = null,
        string $productAccrualType = 'at_beginning',
        float $processingFeePercentage = 5,
    ): array {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Pricing App Co '.$suffix,
            'slug' => 'pricing-app-'.$suffix,
            'code' => 'PAC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Pricing',
            'last_name' => 'Admin',
            'email' => 'pricing-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        Permission::firstOrCreate(['name' => 'loans.create', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'loans.view', 'guard_name' => 'admin']);
        $admin->givePermissionTo(['loans.create', 'loans.view']);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Character Pricing',
            'code' => 'CP-'.$suffix,
            'category' => 'character',
            'accrual_type' => $productAccrualType,
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate '.$suffix,
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => $interestBehavior,
            'rate_input_mode' => $rateInputMode,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => $processingFeePercentage,
            'term_interest_percentage' => $termInterestPercentage,
            'daily_rate' => $dailyRate,
            'weekly_rate' => $weeklyRate,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Group '.$suffix,
            'code' => 'GRP-'.$suffix,
            'risk_level' => 'medium',
            'max_loan_amount' => 50000,
            'max_loan_tenure_months' => 12,
            'is_active' => true,
            'allow_multiple_loans' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Borrower',
            'last_name' => 'Test',
            'email' => 'borrower-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
            'maximum_loan_take' => 50000,
        ]);

        $channel = Channel::create([
            'name' => 'Test Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        return compact('admin', 'product', 'customer', 'group', 'rateType', 'loanRate', 'channel');
    }

    /**
     * @return array<string, mixed>
     */
    private function createCustomerSelfServiceContext(
        string $interestBehavior,
        string $rateInputMode,
        ?float $termInterestPercentage = null,
        float $processingFeePercentage = 5,
    ): array {
        $context = $this->createCharacterApplicationContext(
            $interestBehavior,
            $rateInputMode,
            $termInterestPercentage,
            processingFeePercentage: $processingFeePercentage,
        );

        $context['group']->update(['allow_multiple_loans' => true]);

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function postAdminCalculateRepayment(array $context, float $amount, int $tenureMonths, string $startDate): array
    {
        $response = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-applications.calculate-repayment', [
                $context['product'],
                $context['customer'],
            ]), [
                'loan_amount' => $amount,
                'tenure_months' => $tenureMonths,
                'loan_start_date' => $startDate,
            ]);

        $response->assertOk();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $quote
     */
    private function createAdminCharacterLoan(array $context, array $quote): Loan
    {
        $sessionPayload = [
            'loan_amount' => 10000,
            'tenure_months' => 1,
            'loan_start_date' => $quote['loan_start_date'] ?? '2026-01-01',
            'channel_id' => $context['channel']->id,
            'disbursement_phone_number' => $context['customer']->phone,
            'processing_fee' => $quote['processing_fee'],
            'interest' => $quote['interest'],
            'total_amount' => $quote['booked_total_amount'] ?? $quote['total_amount'],
            'loan_end_date' => $quote['loan_end_date'],
            'days' => $quote['days'],
            'loan_rate_id' => $quote['loan_rate_id'],
            'daily_rate' => $quote['daily_rate'],
            'weekly_rate' => $quote['weekly_rate'],
            'accrual_period' => $quote['accrual_period'],
        ];

        $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $sessionPayload])
            ->post(route('admin.loan-applications.store-character', [
                $context['product'],
                $context['customer'],
            ]))
            ->assertRedirect();

        $loan = Loan::query()->latest('id')->first();
        $this->assertNotNull($loan);

        return $loan;
    }

    private function createActiveLoanForCron(
        string $accrualType,
        string $interestBehavior,
        float $interestAccrued,
        ?string $dailyRate,
        float $processingFee = 500,
        float $totalAmount = 13280,
        float $outstandingBalance = 13280,
    ): Loan {
        $context = $this->createCharacterApplicationContext(
            interestBehavior: $interestBehavior === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL
                ? LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL
                : LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            rateInputMode: LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            termInterestPercentage: 27.8,
        );

        return Loan::create([
            'customer_id' => $context['customer']->id,
            'loan_product_id' => $context['product']->id,
            'customer_group_id' => $context['group']->id,
            'loan_rate_id' => $context['loanRate']->id,
            'channel_id' => $context['channel']->id,
            'loan_number' => Loan::generateLoanNumber($context['product']),
            'principal_amount' => 10000,
            'processing_fee' => $processingFee,
            'processing_fee_percentage' => 5,
            'daily_rate' => $dailyRate,
            'interest_accrued' => $interestAccrued,
            'total_amount' => $totalAmount,
            'outstanding_balance' => $outstandingBalance,
            'tenure_months' => 1,
            'loan_start_date' => '2026-01-01',
            'loan_end_date' => '2026-02-01',
            'first_payment_date' => '2026-02-01',
            'last_payment_date' => '2026-02-01',
            'accrual_type' => $accrualType,
            'interest_behavior' => $interestBehavior,
            'accrual_period' => 'daily',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $context['customer']->phone,
            'last_accrual_date' => '2026-01-01',
        ]);
    }
}
