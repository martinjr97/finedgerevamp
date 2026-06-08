<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPayDayScheduleService;
use App\Services\LoanPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoanRepaymentScheduleTest extends TestCase
{
    use RefreshDatabase;

    private LoanPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(LoanPricingService::class);
    }

    public function test_upfront_flat_schedule_uses_booked_total_and_components(): void
    {
        $loan = $this->createLoanWithPricing(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
            tenureMonths: 1,
        );

        $loan->createPaymentSchedule();
        $schedules = $loan->paymentSchedules;

        $this->assertCount(1, $schedules);
        $this->assertEqualsWithDelta(13280.0, (float) $schedules->sum('expected_amount'), 0.01);
        $this->assertSame('booked_total', $schedules->first()->schedule_basis);
        $this->assertFalse($schedules->first()->is_projected_interest);
        $this->assertEqualsWithDelta(10000.0, (float) $schedules->sum('principal_component'), 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $schedules->sum('fee_component'), 0.01);
        $this->assertEqualsWithDelta(2780.0, (float) $schedules->sum('interest_component'), 0.01);
        $this->assertEqualsWithDelta(13280.0, (float) $loan->outstanding_balance, 0.01);
    }

    public function test_daily_accrual_schedule_uses_projected_total_while_booked_balance_stays_principal_plus_fee(): void
    {
        $loan = $this->createLoanWithPricing(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
            tenureMonths: 1,
        );

        $loan->createPaymentSchedule();
        $schedules = $loan->paymentSchedules;

        $this->assertCount(1, $schedules);
        $this->assertEqualsWithDelta(13280.0, (float) $schedules->sum('expected_amount'), 0.01);
        $this->assertSame('projected_total', $schedules->first()->schedule_basis);
        $this->assertTrue($schedules->first()->is_projected_interest);
        $this->assertEqualsWithDelta(10500.0, (float) $loan->outstanding_balance, 0.01);
        $this->assertEqualsWithDelta(13280.0, $loan->getScheduleExpectedTotal(), 0.01);
        $this->assertEqualsWithDelta(10500.0, $loan->getBookedBalance(), 0.01);
    }

    public function test_three_installment_component_split_sums_exactly(): void
    {
        $loan = $this->createLoanWithPricing(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
            tenureMonths: 3,
        );

        $loan->createPaymentSchedule();
        $schedules = $loan->paymentSchedules;

        $this->assertCount(3, $schedules);
        $this->assertEqualsWithDelta(13280.0, (float) $schedules->sum('expected_amount'), 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $schedules->sum('principal_component'), 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $schedules->sum('fee_component'), 0.01);
        $this->assertEqualsWithDelta(2780.0, (float) $schedules->sum('interest_component'), 0.01);

        foreach ($schedules as $row) {
            $this->assertEqualsWithDelta(
                (float) $row->expected_amount,
                (float) $row->principal_component + (float) $row->interest_component + (float) $row->fee_component,
                0.01
            );
        }
    }

    public function test_government_loan_persists_pay_day_due_dates_on_schedule(): void
    {
        $context = $this->createGovernmentApplicationContext();
        $loanStartDate = Carbon::parse('2026-01-10');
        $tenureMonths = 3;

        $schedule = app(LoanPayDayScheduleService::class)->calculateDueDates(
            $loanStartDate,
            $tenureMonths,
            (int) $context['group']->loan_cut_off_day,
            (int) $context['group']->loan_payment_date,
        );

        $loan = $this->createGovernmentLoan($context, $loanStartDate, $schedule);

        $persistedDates = $loan->paymentSchedules->pluck('due_date')->map->toDateString()->all();
        $this->assertSame($schedule['payment_due_dates'], $persistedDates);
    }

    public function test_government_calculate_repayment_includes_installment_schedule_preview(): void
    {
        $context = $this->createGovernmentApplicationContext();
        $loanStartDate = '2026-01-10';
        $tenureMonths = 3;

        $expectedSchedule = app(LoanPayDayScheduleService::class)->calculateDueDates(
            Carbon::parse($loanStartDate),
            $tenureMonths,
            (int) $context['group']->loan_cut_off_day,
            (int) $context['group']->loan_payment_date,
        );

        $response = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-applications.calculate-repayment', [
                $context['product'],
                $context['customer'],
            ]), [
                'loan_amount' => 10000,
                'tenure_months' => $tenureMonths,
                'loan_start_date' => $loanStartDate,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'installment_amount',
            'repayment_schedule' => [
                ['period_number', 'due_date', 'expected_amount', 'principal_component', 'interest_component', 'fee_component'],
            ],
        ]);

        $schedule = $response->json('repayment_schedule');
        $this->assertCount($tenureMonths, $schedule);
        $this->assertSame($expectedSchedule['payment_due_dates'], array_column($schedule, 'due_date'));
        $this->assertEqualsWithDelta(
            (float) $response->json('total_amount') / $tenureMonths,
            (float) $response->json('installment_amount'),
            0.02
        );
        $this->assertEqualsWithDelta(
            (float) $response->json('total_amount'),
            array_sum(array_column($schedule, 'expected_amount')),
            0.02
        );
    }

    public function test_government_review_page_shows_repayment_schedule(): void
    {
        $context = $this->createGovernmentApplicationContext();
        $loanStartDate = '2026-01-10';
        $tenureMonths = 3;

        $quote = $this->actingAs($context['admin'], 'admin')
            ->postJson(route('admin.loan-applications.calculate-repayment', [
                $context['product'],
                $context['customer'],
            ]), [
                'loan_amount' => 10000,
                'tenure_months' => $tenureMonths,
                'loan_start_date' => $loanStartDate,
            ])
            ->assertOk()
            ->json();

        $sessionPayload = [
            'loan_amount' => 10000,
            'tenure_months' => $tenureMonths,
            'loan_start_date' => $loanStartDate,
            'channel_id' => $context['channel']->id,
            'disbursement_phone_number' => $context['customer']->phone,
            'processing_fee' => $quote['processing_fee'],
            'interest' => $quote['interest'],
            'total_amount' => $quote['total_amount'],
            'loan_end_date' => $quote['loan_end_date'],
            'days' => $quote['days'],
            'loan_rate_id' => $quote['loan_rate_id'],
            'accrual_period' => $quote['accrual_period'],
            'installment_amount' => $quote['installment_amount'],
            'repayment_schedule' => $quote['repayment_schedule'],
        ];

        $reviewResponse = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $sessionPayload])
            ->get(route('admin.loan-applications.review-government', [
                $context['product'],
                $context['customer'],
            ]));

        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Repayment Schedule');
        $reviewResponse->assertSee('Amount Per Installment');
        $reviewResponse->assertSee(number_format((float) $quote['installment_amount'], 2));
        $reviewResponse->assertSee(Carbon::parse($quote['repayment_schedule'][0]['due_date'])->format('d M Y'));
    }

    public function test_government_loan_details_restores_session_values_when_navigating_back(): void
    {
        $context = $this->createGovernmentApplicationContext();

        $sessionPayload = [
            'loan_amount' => 10000,
            'tenure_months' => 3,
            'loan_start_date' => '2026-01-10',
            'channel_id' => $context['channel']->id,
            'disbursement_phone_number' => $context['customer']->phone,
            'processing_fee' => 500,
            'interest' => 2780,
            'total_amount' => 13280,
            'loan_end_date' => '2026-04-28',
            'days' => 108,
            'loan_rate_id' => $context['loanRate']->id,
            'accrual_period' => 'daily',
            'installment_amount' => 4426.67,
            'repayment_schedule' => [
                [
                    'period_number' => 1,
                    'due_date' => '2026-01-28',
                    'expected_amount' => 4426.67,
                ],
            ],
        ];

        $response = $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $sessionPayload])
            ->get(route('admin.loan-applications.loan-details', [
                $context['product'],
                $context['customer'],
            ]));

        $response->assertOk();
        $response->assertSee('value="10000"', false);
        $response->assertSee('value="2026-01-10"', false);
        $response->assertSee('value="3"', false);
        $response->assertSee('restoreSavedCalculation', false);
        $response->assertSee('"total_amount":13280', false);
        $response->assertSee('"tenure_months":3', false);
    }

    public function test_legacy_loan_without_pricing_metadata_uses_booked_total_amount(): void
    {
        $context = $this->createBareLoanContext();

        $loan = Loan::create([
            'customer_id' => $context['customer']->id,
            'loan_product_id' => $context['product']->id,
            'customer_group_id' => $context['group']->id,
            'channel_id' => $context['channel']->id,
            'loan_number' => Loan::generateLoanNumber($context['product']),
            'principal_amount' => 10000,
            'processing_fee' => 500,
            'interest_accrued' => 2000,
            'total_amount' => 12500,
            'outstanding_balance' => 12500,
            'tenure_months' => 2,
            'loan_start_date' => '2026-01-01',
            'loan_end_date' => '2026-03-01',
            'first_payment_date' => '2026-02-01',
            'last_payment_date' => '2026-03-01',
            'accrual_type' => 'at_beginning',
            'status' => 'active',
            'disbursement_status' => 'pending',
            'disbursement_phone_number' => $context['customer']->phone,
        ]);

        $loan->createPaymentSchedule();

        $this->assertEqualsWithDelta(12500.0, (float) $loan->paymentSchedules->sum('expected_amount'), 0.01);
        $this->assertSame('booked_total', $loan->paymentSchedules->first()->schedule_basis);
        $this->assertFalse($loan->paymentSchedules->first()->is_projected_interest);
    }

    public function test_daily_accrual_cron_does_not_duplicate_schedule_rows(): void
    {
        $loan = $this->createLoanWithPricing(
            interestBehavior: LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            termInterestPercentage: 27.8,
            processingFeePercentage: 5,
            tenureMonths: 1,
            status: 'active',
        );

        $loan->update(['disbursement_status' => 'completed']);
        $loan->createPaymentSchedule();

        $scheduleCountBefore = $loan->paymentSchedules()->count();
        $expectedTotalBefore = (float) $loan->paymentSchedules()->sum('expected_amount');

        $this->artisan('loans:accrue-interest', ['--date' => $loan->loan_start_date->toDateString()])
            ->assertSuccessful();

        $loan->refresh();
        $this->assertSame($scheduleCountBefore, $loan->paymentSchedules()->count());
        $this->assertEqualsWithDelta($expectedTotalBefore, (float) $loan->paymentSchedules()->sum('expected_amount'), 0.01);
        $this->assertGreaterThan(0, (float) $loan->interest_accrued);
    }

    /**
     * @return array<string, mixed>
     */
    private function createLoanWithPricing(
        string $interestBehavior,
        float $termInterestPercentage,
        float $processingFeePercentage,
        int $tenureMonths,
        string $status = 'pending_approval',
    ): Loan {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Schedule Co '.$suffix,
            'slug' => 'schedule-co-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Schedule Product',
            'code' => 'SP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Rate '.$suffix,
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => $interestBehavior,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => $tenureMonths,
            'processing_fee_percentage' => $processingFeePercentage,
            'term_interest_percentage' => $termInterestPercentage,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Group '.$suffix,
            'code' => 'GRP-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Schedule',
            'last_name' => 'Borrower',
            'email' => 'schedule-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $channel = Channel::create([
            'name' => 'Channel '.$suffix,
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $loanStartDate = Carbon::parse('2026-01-01');
        $loanEndDate = $loanStartDate->copy()->addMonths($tenureMonths);
        $days = $loanStartDate->diffInDays($loanEndDate);

        $quote = $this->pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => $tenureMonths,
            'start_date' => $loanStartDate->toDateString(),
            'term_days' => $days,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $product,
        ]);

        $financials = $this->pricing->buildLoanFinancialSnapshot($quote);
        $pricingMeta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'loan_rate_id' => $loanRate->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 10000,
            'tenure_months' => $tenureMonths,
            'loan_start_date' => $loanStartDate,
            'loan_end_date' => $loanEndDate,
            'first_payment_date' => $loanStartDate->copy()->addMonth(),
            'last_payment_date' => $loanEndDate,
            'amount_paid' => 0,
            'status' => $status,
            'disbursement_status' => 'pending',
            'disbursement_phone_number' => $customer->phone,
            'metadata' => $pricingMeta,
        ], $financials));
    }

    /**
     * @return array<string, mixed>
     */
    private function createGovernmentApplicationContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Gov Co '.$suffix,
            'slug' => 'gov-co-'.$suffix,
            'code' => 'GC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Gov',
            'last_name' => 'Admin',
            'email' => 'gov-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        Permission::firstOrCreate(['name' => 'loans.create', 'guard_name' => 'admin']);
        $admin->givePermissionTo('loans.create');

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Government',
            'code' => 'GOV-'.$suffix,
            'category' => 'government',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'Gov Rate',
            'code' => 'GR-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'term_interest_percentage' => 27.8,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 3,
            'processing_fee_percentage' => 5,
            'term_interest_percentage' => 27.8,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'Gov Group',
            'code' => 'GG-'.$suffix,
            'risk_level' => 'medium',
            'loan_cut_off_day' => 25,
            'loan_payment_date' => 28,
            'max_loan_amount' => 50000,
            'is_active' => true,
            'allow_multiple_loans' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Gov',
            'last_name' => 'Borrower',
            'email' => 'gov-borrower-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'tpin' => (string) random_int(10000000, 99999999),
            'status' => 'active',
            'approval_status' => 'approved',
            'maximum_loan_take' => 50000,
        ]);

        $channel = Channel::create([
            'name' => 'Gov Channel',
            'code' => 'GCH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        return compact('admin', 'product', 'customer', 'group', 'rateType', 'loanRate', 'channel', 'company');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $schedule
     */
    private function createGovernmentLoan(array $context, Carbon $loanStartDate, array $schedule): Loan
    {
        $sessionPayload = [
            'loan_amount' => 10000,
            'tenure_months' => 3,
            'loan_start_date' => $loanStartDate->toDateString(),
            'channel_id' => $context['channel']->id,
            'disbursement_phone_number' => $context['customer']->phone,
            'processing_fee' => 500,
            'interest' => 2780,
            'total_amount' => 13280,
            'loan_end_date' => $schedule['loan_end_date']->toDateString(),
            'days' => $schedule['days'],
            'loan_rate_id' => $context['loanRate']->id,
            'accrual_period' => 'daily',
        ];

        $this->actingAs($context['admin'], 'admin')
            ->withSession(['loan_application_data' => $sessionPayload])
            ->post(route('admin.loan-applications.store-government', [
                $context['product'],
                $context['customer'],
            ]))
            ->assertRedirect();

        $loan = Loan::query()->latest('id')->first();
        $this->assertNotNull($loan);

        return $loan;
    }

    /**
     * @return array<string, mixed>
     */
    private function createBareLoanContext(): array
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Legacy Co '.$suffix,
            'slug' => 'legacy-'.$suffix,
            'code' => 'LC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Legacy',
            'code' => 'LEG-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'name' => 'Legacy Group',
            'code' => 'LG-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Legacy',
            'last_name' => 'Borrower',
            'email' => 'legacy-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'name' => 'Legacy Ch',
            'code' => 'LCH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        return compact('product', 'group', 'customer', 'channel');
    }
}
