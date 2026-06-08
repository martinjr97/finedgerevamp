<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Loan;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRate;
use App\Models\LoanRateType;
use App\Services\LoanPricingService;
use App\Services\LoanSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoanSettlementTest extends TestCase
{
    use RefreshDatabase;

    private LoanSettlementService $settlement;

    private LoanPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settlement = app(LoanSettlementService::class);
        $this->pricing = app(LoanPricingService::class);
    }

    public function test_upfront_flat_half_term_settlement_earned_interest_and_rebate(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
        );

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->assertSame('upfront_flat', $quote['interest_behavior']);
        $this->assertEqualsWithDelta(1390.0, (float) $quote['interest_earned'], 0.01);
        $this->assertEqualsWithDelta(1390.0, (float) $quote['unearned_interest_rebate'], 0.01);
        $this->assertEqualsWithDelta(11890.0, (float) $quote['payoff_amount'], 0.01);
    }

    public function test_upfront_flat_payoff_includes_non_refundable_processing_fee(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
        );

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->assertEqualsWithDelta(500.0, (float) $quote['processing_fee_remaining'], 0.01);
        $this->assertFalse($quote['processing_fee_refundable']);
        $this->assertGreaterThanOrEqual(
            10500.0,
            (float) $quote['payoff_amount']
        );
    }

    public function test_daily_accrual_settlement_excludes_projected_future_interest(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
        );

        $loan->createPaymentSchedule();
        $scheduleTotal = (float) $loan->paymentSchedules()->sum('expected_amount');

        $this->assertEqualsWithDelta(13280.0, $scheduleTotal, 0.01);

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->assertSame('daily_accrual', $quote['interest_behavior']);
        $this->assertLessThan($scheduleTotal, (float) $quote['payoff_amount']);
        $this->assertEqualsWithDelta(10500.0, (float) $loan->outstanding_balance, 0.01);
        $this->assertEqualsWithDelta(
            10500.0 + (float) $quote['interest_earned'],
            (float) $quote['payoff_amount'],
            0.01
        );
    }

    public function test_quote_settlement_does_not_mutate_loan(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
        );

        $before = $loan->only(['interest_accrued', 'outstanding_balance', 'status', 'rebate_amount']);

        $this->settlement->quoteSettlement($loan, '2026-01-15');
        $loan->refresh();

        $this->assertSame($before['interest_accrued'], $loan->interest_accrued);
        $this->assertSame($before['outstanding_balance'], $loan->outstanding_balance);
        $this->assertSame($before['status'], $loan->status);
        $this->assertSame($before['rebate_amount'], $loan->rebate_amount);
    }

    public function test_apply_settlement_marks_loan_settled_and_stores_snapshot_fields(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
            'active',
        );

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->settlement->applySettlement($loan, [
            'amount' => $quote['payoff_amount'],
            'settlement_date' => '2026-01-15',
            'channel_id' => $loan->channel_id,
        ]);

        $loan->refresh();

        $this->assertSame('settled', $loan->status);
        $this->assertEqualsWithDelta((float) $quote['payoff_amount'], (float) $loan->settlement_amount, 0.01);
        $this->assertSame('2026-01-15', $loan->settlement_date->toDateString());
        $this->assertEqualsWithDelta(1390.0, (float) $loan->rebate_amount, 0.01);
        $this->assertSame('2026-01-15', $loan->loan_settled_date->toDateString());
        $this->assertEqualsWithDelta(0.0, (float) $loan->outstanding_balance, 0.01);
    }

    public function test_overpayment_does_not_create_negative_outstanding_balance(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
            'active',
        );

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->settlement->applySettlement($loan, [
            'amount' => (float) $quote['payoff_amount'] + 5000,
            'settlement_date' => '2026-01-15',
            'channel_id' => $loan->channel_id,
        ]);

        $loan->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $loan->outstanding_balance);
    }

    public function test_legacy_loan_settlement_uses_outstanding_balance(): void
    {
        $loan = $this->createLegacyLoan(12500.0);

        $quote = $this->settlement->quoteSettlement($loan, '2026-02-01');

        $this->assertSame('legacy', $quote['interest_behavior']);
        $this->assertEqualsWithDelta(0.0, (float) $quote['unearned_interest_rebate'], 0.01);
        $this->assertEqualsWithDelta(12500.0, (float) $quote['payoff_amount'], 0.01);
    }

    public function test_projected_schedule_rows_closed_after_settlement(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_DAILY_ACCRUAL,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
            'active',
        );

        $loan->createPaymentSchedule();
        $this->assertGreaterThan(0, $loan->paymentSchedules()->where('remaining_amount', '>', 0)->count());

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->settlement->applySettlement($loan, [
            'amount' => $quote['payoff_amount'],
            'settlement_date' => '2026-01-15',
            'channel_id' => $loan->channel_id,
        ]);

        $this->assertSame(
            0,
            $loan->paymentSchedules()->where('remaining_amount', '>', 0)->count()
        );
        $this->assertSame(
            $loan->paymentSchedules()->count(),
            $loan->paymentSchedules()->whereIn('status', ['paid', 'paid_early'])->count()
        );
    }

    public function test_settlement_rejected_if_loan_already_settled(): void
    {
        $loan = $this->createPricedLoan(
            LoanRateType::INTEREST_BEHAVIOR_UPFRONT_FLAT,
            27.8,
            5,
            '2026-01-01',
            '2026-01-31',
            30,
            'active',
        );

        $quote = $this->settlement->quoteSettlement($loan, '2026-01-15');

        $this->settlement->applySettlement($loan, [
            'amount' => $quote['payoff_amount'],
            'settlement_date' => '2026-01-15',
            'channel_id' => $loan->channel_id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already settled');

        $this->settlement->applySettlement($loan->fresh(), [
            'amount' => $quote['payoff_amount'],
            'settlement_date' => '2026-01-15',
            'channel_id' => $loan->channel_id,
        ]);
    }

    private function createPricedLoan(
        string $interestBehavior,
        float $termPct,
        float $feePct,
        string $start,
        string $end,
        int $termDays,
        string $status = 'active',
    ): Loan {
        $suffix = Str::lower(Str::random(5));
        $company = Company::create([
            'name' => 'Settle Co '.$suffix,
            'slug' => 'settle-'.$suffix,
            'code' => 'SC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $product = LoanProduct::create([
            'company_id' => $company->id,
            'name' => 'Settle Product',
            'code' => 'SP-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $rateType = LoanRateType::create([
            'loan_product_id' => $product->id,
            'name' => 'RT '.$suffix,
            'code' => 'RT-'.$suffix,
            'accrual_period' => 'daily',
            'interest_behavior' => $interestBehavior,
            'rate_input_mode' => LoanRateType::RATE_INPUT_TERM_PERCENTAGE,
            'is_active' => true,
        ]);

        $loanRate = LoanRate::create([
            'loan_rate_type_id' => $rateType->id,
            'tenure_months' => 1,
            'processing_fee_percentage' => $feePct,
            'term_interest_percentage' => $termPct,
            'arrear_rate' => 0.01,
            'is_active' => true,
        ]);

        $group = CustomerGroup::create([
            'loan_product_id' => $product->id,
            'loan_rate_type_id' => $rateType->id,
            'name' => 'G '.$suffix,
            'code' => 'G-'.$suffix,
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'first_name' => 'Settle',
            'last_name' => 'Test',
            'email' => 'settle-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'name' => 'Ch '.$suffix,
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        $quote = $this->pricing->quoteLoan([
            'principal' => 10000,
            'tenure_months' => 1,
            'start_date' => $startDate->toDateString(),
            'term_days' => $termDays,
            'loan_rate' => $loanRate,
            'loan_rate_type' => $rateType,
            'loan_product' => $product,
        ]);

        $financials = $this->pricing->buildLoanFinancialSnapshot($quote);
        $meta = $financials['pricing_metadata'] ?? [];
        unset($financials['pricing_metadata']);
        $meta['term_days'] = $termDays;

        return Loan::create(array_merge([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'customer_group_id' => $group->id,
            'loan_rate_id' => $loanRate->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 10000,
            'tenure_months' => 1,
            'loan_start_date' => $startDate,
            'loan_end_date' => $endDate,
            'first_payment_date' => $startDate->copy()->addMonth(),
            'last_payment_date' => $endDate,
            'amount_paid' => 0,
            'status' => $status,
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
            'last_accrual_date' => $interestBehavior === Loan::INTEREST_BEHAVIOR_DAILY_ACCRUAL
                ? $startDate
                : null,
            'metadata' => $meta,
        ], $financials));
    }

    private function createLegacyLoan(float $outstanding): Loan
    {
        $suffix = Str::lower(Str::random(5));

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
            'name' => 'Legacy '.$suffix,
            'code' => 'LEG-'.$suffix,
            'category' => 'character',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'loan_product_id' => $product->id,
            'first_name' => 'Legacy',
            'last_name' => 'Borrower',
            'email' => 'leg-'.$suffix.'@example.com',
            'phone' => '260955'.random_int(100000, 999999),
            'password' => '1234',
            'status' => 'active',
        ]);

        $channel = Channel::create([
            'name' => 'Ch',
            'code' => 'CH-'.$suffix,
            'can_disburse' => true,
            'can_repay' => true,
            'is_active' => true,
        ]);

        return Loan::create([
            'customer_id' => $customer->id,
            'loan_product_id' => $product->id,
            'channel_id' => $channel->id,
            'loan_number' => Loan::generateLoanNumber($product),
            'principal_amount' => 10000,
            'processing_fee' => 500,
            'interest_accrued' => 2000,
            'total_amount' => $outstanding,
            'outstanding_balance' => $outstanding,
            'amount_paid' => 0,
            'tenure_months' => 2,
            'loan_start_date' => '2026-01-01',
            'loan_end_date' => '2026-03-01',
            'first_payment_date' => '2026-02-01',
            'last_payment_date' => '2026-03-01',
            'accrual_type' => 'at_beginning',
            'status' => 'active',
            'disbursement_status' => 'completed',
            'disbursement_phone_number' => $customer->phone,
        ]);
    }
}
